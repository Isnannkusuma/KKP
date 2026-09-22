<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BackfillQueryEmbeddings extends Command
{
    /**
     * Jalankan: php artisan faq:backfill-embeddings
     * Opsional: --batch=20 untuk atur ukuran batch per panggilan API
     */
    protected $signature = 'faq:backfill-embeddings {--batch=20 : Jumlah query per batch panggilan API embedding}';

    protected $description = 'Isi query_embedding untuk baris search_audit_logs lama yang belum punya embedding';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');

        $totalMissing = DB::table('search_audit_logs')
            ->whereNull('query_embedding')
            ->count();

        if ($totalMissing === 0) {
            $this->info('Semua baris sudah punya query_embedding. Tidak ada yang perlu di-backfill.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$totalMissing} baris tanpa query_embedding. Memproses dalam batch {$batchSize}...");

        $pythonUrl = rtrim(
            config('services.python_ai.base_url', 'http://localhost:8000'),
            '/'
        ).'/embed/queries';

        $processed = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($totalMissing);
        $bar->start();

        while (true) {
            // Ambil batch baris yang query_embedding-nya masih NULL
            $rows = DB::table('search_audit_logs')
                ->whereNull('query_embedding')
                ->orderBy('created_at')
                ->limit($batchSize)
                ->get(['id', 'query']);

            if ($rows->isEmpty()) {
                break;
            }

            $queries = $rows->pluck('query')->all();

            try {
                $response = Http::timeout(60)
                    ->post($pythonUrl, ['queries' => $queries]);

                if (! $response->successful()) {
                    $this->newLine();
                    $this->error("Batch gagal (HTTP {$response->status()}): ".$response->body());
                    Log::error('Backfill embedding batch gagal', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    $failed += $rows->count();

                    // Skip batch ini supaya tidak infinite loop pada baris yang sama,
                    // dengan menandai sementara agar tidak diambil ulang di iterasi berikutnya.
                    // (opsional: bisa juga break total jika ingin berhenti saat ada error)
                    DB::table('search_audit_logs')
                        ->whereIn('id', $rows->pluck('id'))
                        ->update(['query_embedding' => DB::raw("'[".implode(',', array_fill(0, 768, 0))."]'::vector")]);

                    $bar->advance($rows->count());

                    continue;
                }

                $embeddings = $response->json('embeddings', []);

                if (count($embeddings) !== $rows->count()) {
                    $this->newLine();
                    $this->error('Jumlah embedding tidak sesuai jumlah query yang dikirim. Batch dilewati.');
                    $failed += $rows->count();
                    $bar->advance($rows->count());

                    continue;
                }

                foreach ($rows as $index => $row) {
                    $vectorString = '['.implode(',', $embeddings[$index]).']';

                    DB::statement(
                        'UPDATE search_audit_logs SET query_embedding = ?::vector WHERE id = ?',
                        [$vectorString, $row->id]
                    );
                }

                $processed += $rows->count();
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error('Exception saat memanggil embedding service: '.$e->getMessage());
                Log::error('Backfill embedding exception', ['message' => $e->getMessage()]);
                $failed += $rows->count();

                // Hindari infinite loop: tandai baris ini supaya tidak diproses ulang terus-menerus
                DB::table('search_audit_logs')
                    ->whereIn('id', $rows->pluck('id'))
                    ->update(['query_embedding' => DB::raw("'[".implode(',', array_fill(0, 768, 0))."]'::vector")]);
            }

            $bar->advance($rows->count());
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Selesai. Berhasil diproses: {$processed}, gagal/dilewati: {$failed}.");

        return self::SUCCESS;
    }
}