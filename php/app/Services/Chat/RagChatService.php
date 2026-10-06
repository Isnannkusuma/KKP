<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RagChatService
{
    /**
     * Ambang cosine distance untuk FAQ.
     * Semakin kecil berarti semakin mirip.
     */
    protected float $faqMatchThreshold = 0.15;

    /**
     * Ambang hybrid score untuk hasil RAG.
     */
    protected float $ragScoreThreshold = 0.35;

    /**
     * Pesan ketika pertanyaan di luar lingkup RAG.
     */
    protected string $outOfScopeMessage =
        'Maaf, pertanyaan tersebut berada di luar lingkup layanan Asisten AI ini. '
        .'Saya hanya dapat membantu terkait informasi kedinasan/instansi berdasarkan '
        .'dokumen resmi yang tersedia. Silakan ajukan pertanyaan lain yang berkaitan.';

    /**
     * Memproses pertanyaan menggunakan RAG saja.
     *
     * Tidak menggunakan:
     * - login
     * - NIP
     * - data pegawai
     * - Bangkom
     * - pemetaan
     * - Portal Pegawai
     */
    public function process(string $message, array $history = []): string
    {
        $message = trim($message);

        if ($message === '') {
            return $this->outOfScopeMessage;
        }

        $retrievalQuery = QueryNormalizer::normalize($message);

        try {
            /*
             * ============================================================
             * 1. EMBEDDING QUERY
             * ============================================================
             */
            $queryEmbedding = $this->embedQuery($retrievalQuery);

            /*
             * ============================================================
             * 2. FAQ MATCH
             * ============================================================
             *
             * Jika pertanyaan sangat mirip dengan FAQ yang sudah approved,
             * langsung gunakan jawaban FAQ tanpa memanggil LLM.
             */
            if (! empty($queryEmbedding)) {
                $faqMatch = $this->findMatchingFaq($queryEmbedding);

                if ($faqMatch !== null) {
                    return $this->sanitizeAiAnswer(
                        (string) $faqMatch->answer
                    );
                }
            }

            /*
             * ============================================================
             * 3. RAG SEARCH
             * ============================================================
             */
            $ragResult = $this->searchRag(
                $retrievalQuery,
                $queryEmbedding
            );

            $contextParts = $ragResult['context'];
            $sources = $ragResult['sources'];

            /*
             * Kalau Python AI mengembalikan embedding dan embedding awal
             * gagal, gunakan embedding dari hasil search.
             */
            if (
                empty($queryEmbedding)
                && ! empty($ragResult['query_embedding'])
            ) {
                $queryEmbedding = $ragResult['query_embedding'];
            }

            /*
             * Tidak ada dokumen relevan.
             */
            if ($contextParts === []) {
                return $this->outOfScopeMessage;
            }

            /*
             * ============================================================
             * 4. BUILD CONTEXT
             * ============================================================
             */
            $context = implode("\n\n", $contextParts);

            $historyText = $this->formatChatHistory($history);

            /*
             * ============================================================
             * 5. GENERATE AI ANSWER
             * ============================================================
             */
            $answer = $this->generateAnswer(
                message: $message,
                context: $context,
                history: $historyText,
            );

            /*
             * ============================================================
             * 6. FALLBACK KE DOKUMEN
             * ============================================================
             *
             * Jika Gemini dan Groq gagal tetapi RAG tersedia,
             * tetap berikan informasi dari dokumen.
             */
            if ($answer === null) {
                return $this->documentContextFallback($contextParts);
            }

            return $this->sanitizeAiAnswer($answer);

        } catch (\Throwable $e) {
            Log::error('RAG chat service error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 'Maaf, terjadi kesalahan saat memproses pertanyaan. Silakan coba lagi.';
        }
    }

    /**
     * Membuat embedding untuk query.
     */
    protected function embedQuery(string $text): ?array
    {
        $pythonUrl = rtrim(
            config('services.python_ai.base_url', 'http://localhost:8000'),
            '/'
        ).'/embed/queries';

        try {
            $response = Http::connectTimeout(3)
                ->timeout(8)
                ->post($pythonUrl, [
                    'queries' => [$text],
                ]);

            if (! $response->successful()) {
                Log::warning('Gagal mendapatkan query embedding', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json('embeddings.0');

        } catch (\Throwable $e) {
            Log::warning('Gagal embed query', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Mencari FAQ yang paling mirip.
     */
    protected function findMatchingFaq(array $queryEmbedding): ?object
    {
        if ($queryEmbedding === []) {
            return null;
        }

        $vectorString = '['.implode(',', $queryEmbedding).']';

        $match = DB::selectOne(
            "SELECT id, question, answer,
                    question_embedding <=> ?::vector AS distance
             FROM faq_entries
             WHERE status = 'approved'
             ORDER BY distance ASC
             LIMIT 1",
            [$vectorString]
        );

        if (
            $match === null
            || (float) $match->distance > $this->faqMatchThreshold
        ) {
            return null;
        }

        return $match;
    }

    /**
     * Search RAG melalui Python AI service.
     */
    protected function searchRag(
        string $message,
        ?array $queryEmbedding = null
    ): array {
        $pythonUrl = rtrim(
            config('services.python_ai.base_url', 'http://localhost:8000'),
            '/'
        ).'/search';

        try {
            $payload = [
                'query' => $message,
                'top_k' => 3,
            ];

            if (! empty($queryEmbedding)) {
                $payload['query_embedding'] = $queryEmbedding;
            }

            $response = Http::connectTimeout(3)
                ->timeout(8)
                ->asJson()
                ->post($pythonUrl, $payload);

            if (! $response->successful()) {
                Log::warning('RAG search gagal', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'context' => [],
                    'sources' => [],
                    'query_embedding' => null,
                ];
            }

            $data = $response->json();

            $results = $data['results'] ?? [];

            $contextParts = [];
            $sources = [];

            foreach ($results as $index => $result) {
                $score = (float) ($result['hybrid_score'] ?? 0);

                /*
                 * Hanya gunakan dokumen yang cukup relevan.
                 */
                if ($score < $this->ragScoreThreshold) {
                    continue;
                }

                $content = trim((string) ($result['content'] ?? ''));

                if ($content === '') {
                    continue;
                }

                $contextParts[] =
                    'SUMBER '.(count($contextParts) + 1).":\n"
                    .$content;

                $documentId = $result['document_id'] ?? null;

                $sources[] = [
                    'document_id' => $documentId,
                    'score' => $score,
                ];
            }

            return [
                'context' => $contextParts,
                'sources' => $sources,
                'query_embedding' => $data['query_embedding'] ?? null,
            ];

        } catch (\Throwable $e) {
            Log::error('Exception saat RAG search', [
                'message' => $e->getMessage(),
            ]);

            return [
                'context' => [],
                'sources' => [],
                'query_embedding' => null,
            ];
        }
    }

    /**
     * Generate jawaban menggunakan Gemini lalu Groq sebagai fallback.
     */
    protected function generateAnswer(
        string $message,
        string $context,
        string $history = ''
    ): ?string {
        $systemPrompt = <<<PROMPT
Anda adalah Asisten Layanan resmi instansi.

Tugas Anda adalah menjawab pertanyaan pengguna berdasarkan konteks
dokumen resmi yang diberikan.

ATURAN:
1. Gunakan informasi dari konteks dokumen sebagai sumber utama.
2. Jangan mengarang informasi yang tidak terdapat dalam konteks.
3. Untuk pertanyaan mengenai kebijakan, peraturan, prosedur, layanan,
   atau informasi kedinasan, jangan menggunakan asumsi atau pengetahuan
   umum di luar konteks.
4. Jika konteks tidak cukup untuk menjawab pertanyaan, katakan bahwa
   informasi tersebut tidak ditemukan dalam dokumen resmi yang tersedia.
5. Jawab dalam Bahasa Indonesia yang jelas, natural, dan mudah dipahami.
6. Jangan menyebut bahwa Anda adalah model AI kecuali memang diperlukan.
7. Jangan membahas data pribadi atau data kepegawaian pengguna.
8. Jangan meminta NIP, password, atau kredensial pengguna.
9. Jangan membuat informasi resmi baru yang tidak ada di dokumen.

KONTEKS DOKUMEN:
{$context}
PROMPT;

        /*
         * ============================================================
         * GEMINI
         * ============================================================
         */
        $geminiKey = config('services.gemini.key');

        if (! empty($geminiKey)) {
            try {
                $geminiUrl =
                    'https://generativelanguage.googleapis.com/'
                    .'v1beta/models/gemini-3.5-flash:generateContent';

                $contents = [];

                if ($history !== '') {
                    $contents[] = [
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => "Riwayat percakapan:\n{$history}",
                            ],
                        ],
                    ];
                }

                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => "Pertanyaan pengguna:\n{$message}",
                        ],
                    ],
                ];

                $response = Http::withoutVerifying()
                    ->connectTimeout(5)
                    ->timeout(30)
                    ->withHeaders([
                        'x-goog-api-key' => $geminiKey,
                    ])
                    ->post($geminiUrl, [
                        'system_instruction' => [
                            'parts' => [
                                [
                                    'text' => $systemPrompt,
                                ],
                            ],
                        ],
                        'contents' => $contents,
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'maxOutputTokens' => 2048,
                        ],
                    ]);

                if ($response->successful()) {
                    $answer = $response->json(
                        'candidates.0.content.parts.0.text'
                    );

                    if (is_string($answer) && trim($answer) !== '') {
                        return trim($answer);
                    }
                }

                Log::warning('Gemini RAG request gagal', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            } catch (\Throwable $e) {
                Log::warning('Gemini RAG exception', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        /*
         * ============================================================
         * GROQ FALLBACK
         * ============================================================
         */
        $groqKey = config('services.groq.key');

        if (! empty($groqKey)) {
            try {
                $groqUrl =
                    'https://api.groq.com/openai/v1/chat/completions';

                $model = config(
                    'services.groq.model',
                    'openai/gpt-oss-20b'
                );

                $userPrompt = $message;

                if ($history !== '') {
                    $userPrompt =
                        "Riwayat percakapan:\n"
                        .$history
                        ."\n\nPertanyaan pengguna:\n"
                        .$message;
                }

                $response = Http::withoutVerifying()
                    ->connectTimeout(5)
                    ->timeout(30)
                    ->withToken($groqKey)
                    ->post($groqUrl, [
                        'model' => $model,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => $systemPrompt,
                            ],
                            [
                                'role' => 'user',
                                'content' => $userPrompt,
                            ],
                        ],
                        'temperature' => 0.2,
                        'max_tokens' => 2048,
                    ]);

                if ($response->successful()) {
                    $answer = $response->json(
                        'choices.0.message.content'
                    );

                    if (is_string($answer) && trim($answer) !== '') {
                        return trim($answer);
                    }
                }

                Log::warning('Groq RAG request gagal', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            } catch (\Throwable $e) {
                Log::warning('Groq RAG exception', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Format history percakapan.
     */
    protected function formatChatHistory(array $history): string
    {
        if ($history === []) {
            return '';
        }

        return collect($history)
            ->map(
                fn (array $entry): string => ucfirst($entry['role'] ?? 'user')
                    .': '
                    .trim((string) ($entry['content'] ?? ''))
            )
            ->filter(
                fn (string $entry): bool => $entry !== ''
            )
            ->implode("\n");
    }

    /**
     * Membersihkan output AI.
     */
    protected function sanitizeAiAnswer(string $answer): string
    {
        $answer = trim($answer);

        $answer = preg_replace(
            '/(?:\R|^)[ \t]*(?:[*-][ \t]*)?Is it safer to display all fields\?.*$/is',
            '',
            $answer
        ) ?? $answer;

        return trim($answer);
    }

    /**
     * Fallback jika provider AI tidak tersedia.
     */
    protected function documentContextFallback(
        array $contextParts
    ): string {
        $documentContent = collect($contextParts)
            ->map(
                fn (string $context): string => preg_replace(
                    '/^SUMBER \d+:\s*/',
                    '',
                    $context
                ) ?? $context
            )
            ->filter(
                fn (string $context): bool => trim($context) !== ''
            )
            ->implode("\n\n");

        return
            'Layanan AI sedang tidak tersedia. Berikut informasi '
            .'dari dokumen resmi yang relevan:'."\n\n"
            .$documentContent;
    }
}
