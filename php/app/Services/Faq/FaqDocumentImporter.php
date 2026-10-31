<?php

namespace App\Services\Faq;

use App\Models\FaqEntry;
use App\Models\FaqSourceDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FaqDocumentImporter
{
    public function import(FaqSourceDocument $document, array $pairs, string $pythonUrl): array
    {
        $questions = collect($pairs)
            ->filter(fn ($pair) => is_array($pair)
                && is_string($pair['question'] ?? null)
                && is_string($pair['answer'] ?? null)
                && trim($pair['question']) !== ''
                && trim($pair['answer']) !== '')
            ->map(fn ($pair) => [
                'question' => trim($pair['question']),
                'answer' => trim($pair['answer']),
            ])
            ->unique(fn ($pair) => mb_strtolower($pair['question']))
            ->values();

        $created = 0;
        $duplicates = 0;
        $failed = 0;

        foreach ($questions->chunk(20) as $batch) {
            try {
                $response = Http::timeout(60)->post("{$pythonUrl}/embed/queries", [
                    'queries' => $batch->pluck('question')->all(),
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Gagal memanggil embedding untuk FAQ source', [
                    'faq_source_document_id' => $document->id,
                    'message' => $exception->getMessage(),
                ]);
                $failed += $batch->count();

                continue;
            }

            if (! $response->successful()) {
                $failed += $batch->count();

                continue;
            }

            $embeddings = $response->json('embeddings', []);
            if (count($embeddings) !== $batch->count()) {
                $failed += $batch->count();

                continue;
            }

            foreach ($batch->values() as $index => $pair) {
                $vectorString = '['.implode(',', $embeddings[$index]).']';

                try {
                    $closest = DB::selectOne(
                        'SELECT question_embedding <=> ?::vector AS distance
                         FROM faq_entries
                         WHERE question_embedding IS NOT NULL
                         ORDER BY distance ASC
                         LIMIT 1',
                        [$vectorString]
                    );

                    if ($closest && (float) $closest->distance < 0.10) {
                        $duplicates++;

                        continue;
                    }

                    $id = (string) Str::uuid();
                    FaqEntry::query()->create([
                        'id' => $id,
                        'cluster_label' => null,
                        'source_count' => 1,
                        'question' => $pair['question'],
                        'answer' => $pair['answer'],
                        'status' => 'draft',
                        'source' => 'document',
                        'source_document_id' => $document->id,
                    ]);
                    DB::statement(
                        'UPDATE faq_entries SET question_embedding = ?::vector WHERE id = ?',
                        [$vectorString, $id]
                    );
                    $created++;
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::warning('Gagal menyimpan FAQ dari FAQ source', [
                        'faq_source_document_id' => $document->id,
                        'question' => $pair['question'],
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $document->update([
            'status' => 'ready',
            'faq_count' => $document->faqEntries()->count(),
            'error_message' => $failed > 0 ? "{$failed} FAQ gagal diekstrak." : null,
        ]);

        return compact('created', 'duplicates', 'failed');
    }
}
