<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateFaqFromClusters extends Command
{
    /**
     * Jalankan: php artisan faq:generate-from-clusters
     * Contoh atur ambang batas: php artisan faq:generate-from-clusters --min-samples=8
     */
    protected $signature = 'faq:generate-from-clusters
        {--min-samples=5 : Minimal jumlah pertanyaan mirip supaya dianggap cluster populer}
        {--eps=0.3 : Ambang jarak cosine DBSCAN (semakin kecil = semakin ketat kemiripannya)}
        {--dedup-threshold=0.1 : Ambang cosine distance untuk anggap FAQ baru duplikat dari yang sudah ada}
        {--days=90 : Hanya ambil pertanyaan dari N hari terakhir}';

    protected $description = 'Kelompokkan pertanyaan populer dari search_audit_logs dan buat draft FAQ otomatis via LLM';

    public function handle(): int
    {
        $minSamples = (int) $this->option('min-samples');
        $eps = (float) $this->option('eps');
        $dedupThreshold = (float) $this->option('dedup-threshold');
        $days = (int) $this->option('days');

        $this->info("Mengambil pertanyaan {$days} hari terakhir yang sudah punya embedding...");

        // Ambil id, query, dan embedding (dalam bentuk teks) dari baris yang relevan.
        // Hanya pertanyaan yang berhasil dijawab (bukan error/unanswered) yang dipakai,
        // supaya FAQ yang di-generate berkualitas.
        $rows = DB::select("
            SELECT id, query, answer, query_embedding::text AS embedding_text
            FROM search_audit_logs
            WHERE query_embedding IS NOT NULL
              AND response_status = 'answered'
              AND created_at > NOW() - INTERVAL '{$days} days'
        ");

        if (count($rows) < $minSamples) {
            $this->warn('Jumlah pertanyaan tidak cukup untuk clustering. Minimal: '.$minSamples.', tersedia: '.count($rows));

            return self::SUCCESS;
        }

        $this->info(count($rows).' pertanyaan ditemukan. Mengirim ke service clustering...');

        $items = collect($rows)->map(fn ($row) => [
            'id' => $row->id,
            'query' => $row->query,
            'embedding' => $this->parseVectorText($row->embedding_text),
        ])->values()->all();

        $pythonUrl = rtrim(
            config('services.python_ai.base_url', 'http://localhost:8000'),
            '/'
        ).'/cluster';

        try {
            $response = Http::timeout(60)->post($pythonUrl, [
                'items' => $items,
                'eps' => $eps,
                'min_samples' => $minSamples,
            ]);
        } catch (\Throwable $e) {
            $this->error('Gagal menghubungi service clustering: '.$e->getMessage());
            Log::error('Clustering request exception', ['message' => $e->getMessage()]);

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Service clustering mengembalikan error: '.$response->body());

            return self::FAILURE;
        }

        $clusters = $response->json('clusters', []);
        $totalNoise = $response->json('total_noise', 0);

        $this->info(count($clusters)." cluster ditemukan ({$totalNoise} pertanyaan dianggap unik/noise).");

        if (empty($clusters)) {
            $this->info('Tidak ada cluster yang cukup besar untuk dijadikan FAQ. Selesai.');

            return self::SUCCESS;
        }

        // Petakan id -> answer, untuk dipakai sebagai referensi jawaban asli saat generate via LLM
        $answersById = collect($rows)->keyBy('id')->map(fn ($row) => $row->answer);

        $created = 0;
        $skippedDuplicate = 0;
        $skippedFailed = 0;

        foreach ($clusters as $cluster) {
            $this->line("Memproses cluster '{$cluster['representative_question']}' ({$cluster['member_count']} anggota)...");

            $sampleAnswers = collect($cluster['sample_questions'])
                ->map(function ($question, $index) use ($cluster, $answersById) {
                    $id = $cluster['member_ids'][$index] ?? null;

                    return $id && $answersById->has($id)
                        ? "Q: {$question}\nA: ".Str::limit($answersById[$id], 300)
                        : "Q: {$question}";
                })
                ->implode("\n\n");

            $generated = $this->generateFaqWithLlm($sampleAnswers);

            if ($generated === null) {
                $this->warn('  Gagal generate FAQ via LLM untuk cluster ini. Dilewati.');
                $skippedFailed++;

                continue;
            }

            [$faqQuestion, $faqAnswer] = $generated;

            // Embed pertanyaan FAQ hasil generate, untuk dipakai saat cek duplikat & RAG lookup nanti
            $questionEmbedding = $this->embedText($faqQuestion);

            if ($questionEmbedding === null) {
                $this->warn('  Gagal embed pertanyaan FAQ hasil generate. Dilewati.');
                $skippedFailed++;

                continue;
            }

            $vectorString = '['.implode(',', $questionEmbedding).']';

            // Cek duplikat: apakah sudah ada FAQ (draft/approved/rejected) yang sangat mirip?
            $closest = DB::selectOne(
                'SELECT id, question, question_embedding <=> ?::vector AS distance
                 FROM faq_entries
                 ORDER BY distance ASC
                 LIMIT 1',
                [$vectorString]
            );

            if ($closest && $closest->distance < $dedupThreshold) {
                $this->line("  Dilewati: sudah ada FAQ mirip \"{$closest->question}\" (distance: ".round($closest->distance, 4).')');
                $skippedDuplicate++;

                continue;
            }

            $id = (string) Str::uuid();

            DB::table('faq_entries')->insert([
                'id' => $id,
                'cluster_label' => $cluster['label'],
                'source_count' => $cluster['member_count'],
                'question' => $faqQuestion,
                'answer' => $faqAnswer,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::statement(
                'UPDATE faq_entries SET question_embedding = ?::vector WHERE id = ?',
                [$vectorString, $id]
            );

            $this->info("  ✅ Draft FAQ dibuat: \"{$faqQuestion}\"");
            $created++;
        }

        $this->newLine();
        $this->info("Selesai. Draft baru: {$created}, duplikat dilewati: {$skippedDuplicate}, gagal: {$skippedFailed}.");

        return self::SUCCESS;
    }

    /**
     * Panggil LLM untuk merangkum kumpulan pertanyaan+jawaban mirip menjadi
     * 1 pertanyaan FAQ representatif + jawaban final.
     *
     * Groq dicoba LEBIH DULU untuk job batch ini (bukan Gemini), karena kuota
     * gratis Gemini (gemini-3.5-flash) hanya 20 request/hari dan kuota itu
     * harus diprioritaskan untuk chatbot live yang melayani user secara
     * real-time. Gemini hanya dipakai sebagai fallback kalau Groq gagal.
     *
     * Fallback berlaku baik saat API call gagal total MAUPUN saat API
     * berhasil merespons tapi hasilnya bukan JSON valid (misal terpotong
     * karena kehabisan token) - supaya tidak diam-diam menerima FAQ rusak.
     *
     * @return array{0: string, 1: string}|null [pertanyaan, jawaban] atau null kalau gagal
     */
    protected function generateFaqWithLlm(string $sampleQaText): ?array
    {
        // Batasi jumlah sample Q&A yang dikirim ke prompt supaya tetap fokus
        // dan tidak membuat model "kebingungan" merangkum terlalu banyak topik
        // sekaligus (yang berujung jawaban ngelantur/terpotong).
        $limitedSample = collect(explode("\n\n", $sampleQaText))->take(10)->implode("\n\n");

        $prompt = "Berikut kumpulan pertanyaan dari pengguna yang topiknya mirip, beserta jawaban "
            ."yang pernah diberikan sebelumnya:\n\n{$limitedSample}\n\n"
            .'Tugas Anda: buat SATU pertanyaan FAQ yang paling mewakili semua pertanyaan di atas, '
            .'dan SATU jawaban final yang jelas, akurat, dan RINGKAS (maksimal 100 kata) yang '
            .'merangkum informasi dari jawaban-jawaban sebelumnya (jangan mengarang informasi baru '
            .'yang tidak ada di jawaban-jawaban tersebut). Gunakan bahasa Indonesia baku dan sopan.'."\n\n"
            .'Balas HANYA dalam format JSON persis seperti ini, tanpa teks tambahan apa pun, '
            ."tanpa markdown code block, dan pastikan JSON valid serta lengkap:\n"
            .'{"question": "...", "answer": "..."}';

        $result = $this->tryParseFaqJson($this->callGroq($prompt), 'Groq')
            ?? $this->tryParseFaqJson($this->callGemini($prompt), 'Gemini');

        return $result;
    }

    /**
     * Coba parse output mentah LLM sebagai JSON {question, answer}.
     * Mengembalikan null (bukan exception) kalau gagal, supaya caller bisa
     * lanjut coba provider berikutnya.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function tryParseFaqJson(?string $raw, string $providerLabel): ?array
    {
        if ($raw === null) {
            return null;
        }

        $cleaned = trim(preg_replace('/```json|```/', '', $raw));
        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded) || empty($decoded['question']) || empty($decoded['answer'])) {
            Log::warning("Hasil LLM ({$providerLabel}) tidak sesuai format JSON yang diharapkan", ['raw' => $raw]);

            return null;
        }

        return [trim($decoded['question']), trim($decoded['answer'])];
    }

    protected function callGemini(string $prompt): ?string
    {
        $apiKey = config('ai.providers.gemini.key');

        if (empty($apiKey)) {
            return null;
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(30)
                ->withoutVerifying()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent', [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 2048,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini gagal saat generate FAQ', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $parts = $response->json('candidates.0.content.parts', []);
            $text = implode('', array_map(fn ($p) => $p['text'] ?? '', $parts));

            return trim($text) !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::warning('Gemini exception saat generate FAQ', ['message' => $e->getMessage()]);

            return null;
        }
    }

    protected function callGroq(string $prompt): ?string
    {
        $apiKey = config('ai.providers.groq.key');

        if (empty($apiKey)) {
            return null;
        }

        try {
            $model = config('ai.providers.groq.model', 'openai/gpt-oss-20b');

            $response = Http::connectTimeout(5)
                ->timeout(30)
                ->withToken($apiKey)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.2,
                    'max_tokens' => 2048,
                ]);

            if (! $response->successful()) {
                Log::warning('Groq gagal saat generate FAQ', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $content = $response->json('choices.0.message.content');

            return is_string($content) && trim($content) !== '' ? $content : null;
        } catch (\Throwable $e) {
            Log::warning('Groq exception saat generate FAQ', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Embed satu teks lewat endpoint /embed/queries (kirim sebagai array 1 item).
     *
     * @return array<float>|null
     */
    protected function embedText(string $text): ?array
    {
        $pythonUrl = rtrim(
            config('services.python_ai.base_url', 'http://localhost:8000'),
            '/'
        ).'/embed/queries';

        try {
            $response = Http::timeout(30)->post($pythonUrl, ['queries' => [$text]]);

            if (! $response->successful()) {
                return null;
            }

            return $response->json('embeddings.0');
        } catch (\Throwable $e) {
            Log::warning('Embed text exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Parse hasil ::text dari kolom pgvector, format "[0.1,0.2,...]", jadi array float PHP.
     *
     * @return array<float>
     */
    protected function parseVectorText(string $vectorText): array
    {
        $trimmed = trim($vectorText, '[]');

        if ($trimmed === '') {
            return [];
        }

        return array_map('floatval', explode(',', $trimmed));
    }
}