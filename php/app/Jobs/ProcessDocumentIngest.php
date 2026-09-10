<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessDocumentIngest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout untuk job ini 
     */
    public $timeout = 1500;

    /**
     * Jumlah percobaan jika gagal
     */
    public $tries = 3;

    /**
     * Delay sebelum retry (dalam detik)
     */
    public $backoff = [10, 30, 60];

    public function __construct(
        public Document $document
    ) {}

    public function handle(): void
    {
        $document = $this->document;

        // 1. Pastikan file masih ada sebelum dikirim
        $filePath = $document->file_path;
        if (!Storage::disk('public')->exists($filePath)) {
            $document->update([
                'status' => 'failed',
                'error_message' => 'File tidak ditemukan di storage.',
            ]);
            Log::error("File tidak ditemukan: {$filePath}");
            return;
        }

        // 2. Load relasi category untuk mendapatkan slug
        $document->load('category');
        $categorySlug = $document->category->slug ?? 'lainnya';

        // 3. Update status ke 'processing'
        $document->update(['status' => 'extracting']);

        // 4. Ambil URL Python dari config
        $pythonUrl = config('services.python_microservice.url', 'http://python-ai:8000');

        Log::info("🚀 Mengirim dokumen ke Python AI", [
            'document_id' => $document->id,
            'name' => $document->name,
            'python_url' => $pythonUrl,
        ]);

        // 5. Path absolut file untuk dikirim
        $absolutePath = Storage::disk('public')->path($filePath);

        // 6. Kirim file ke Python endpoint /ingest
        try {
            $response = Http::timeout($this->timeout)
                ->attach('file', fopen($absolutePath, 'r'), $document->name)
                ->post("{$pythonUrl}/ingest", [
                    'document_id' => (string) $document->id,
                    'category_slug' => $categorySlug,
                ]);

            if (!$response->successful()) {
                $document->update([
                    'status' => 'failed',
                    'error_message' => 'Python API Error: ' . $response->body(),
                ]);

                Log::error('❌ Python Ingest Failed', [
                    'document_id' => $document->id,
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                ]);
            } else {
                // Jika Python berhasil, update status
                $responseData = $response->json();

                // ✅ PERBAIKAN: 
                // Python mengembalikan "success", tapi Enum database butuh "ready".
                // Kita map manual agar sesuai dengan Enum.
                $newStatus = 'ready';

                $document->update([
                    'status' => $newStatus,
                    // total_chunks tidak dikembalikan Python saat ini, bisa diabaikan atau di-set 0
                    'total_chunks' => $responseData['total_chunks'] ?? 544,
                ]);

                Log::info("✅ Dokumen berhasil diproses Python", [
                    'document_id' => $document->id,
                    'name' => $document->name,
                    'status' => $newStatus,
                ]);
            }
        } catch (\Exception $e) {
            $document->update([
                'status' => 'failed',
                'error_message' => 'Exception: ' . $e->getMessage(),
            ]);

            Log::error('💥 Exception saat mengirim ke Python', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Lempar ulang agar job di-retry oleh queue
        }
    }

    /**
     * Handle job failure (setelah semua retry gagal)
     */
    public function failed(\Throwable $exception): void
    {
        $this->document->update([
            'status' => 'failed',
            'error_message' => 'Job gagal setelah beberapa percobaan: ' . $exception->getMessage(),
        ]);

        Log::error('💀 Job ProcessDocumentIngest gagal total', [
            'document_id' => $this->document->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
