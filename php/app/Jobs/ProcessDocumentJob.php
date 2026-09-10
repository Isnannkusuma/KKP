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

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 menit (Sudah sangat aman)

    public function __construct(
        public int $documentId
    ) {}

    public function handle(): void
    {
        $document = Document::find($this->documentId);

        if (!$document) {
            Log::error("Document {$this->documentId} not found in queue.");
            return;
        }

        try {
            $document->update(['status' => 'extracting', 'error_message' => null]);

            // Dapatkan path absolut file
            $filePath = Storage::disk('public')->path($document->file_path);

            if (!file_exists($filePath)) {
                throw new \Exception("File fisik tidak ditemukan di: {$filePath}");
            }

            // PERBAIKAN 1: Tentukan URL dengan benar
            // JIKA Laravel di Shared Hosting & Python di VPS: Gunakan IP VPS atau Domain VPS
            // JIKA Keduanya di Docker yang sama: Gunakan 'http://ai-service:8000'
            $pythonUrl = env('PYTHON_AI_URL', 'http://python-ai:8000');

            $fullUrl = rtrim($pythonUrl, '/') . '/process-document';

            // Logging untuk debugging (Cek di storage/logs/laravel.log)
            Log::info("Mengirim dokumen ke AI Service", [
                'document_id' => $document->id,
                'url' => $fullUrl,
                'file_path' => $filePath,
                'file_size' => filesize($filePath) . ' bytes'
            ]);

            $response = Http::timeout($this->timeout)
                ->attach('file', file_get_contents($filePath), $document->name) // <--- PERBAIKAN DI SINI
                ->asMultipart()
                ->post($fullUrl, [
                    'document_id' => (int) $document->id,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                $document->update([
                    'status' => 'ready',
                    'total_chunks' => $data['total_chunks'] ?? 0,
                ]);

                Log::info("Document {$document->id} processed successfully.", [
                    'chunks' => $data['total_chunks'] ?? 0
                ]);
            } else {
                $errorMsg = $response->json('detail', $response->body());
                throw new \Exception("Python service rejected request: {$errorMsg}");
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Ini adalah cURL Error spesifik (timeout, host tidak ditemukan, koneksi ditolak)
            Log::error("cURL Connection Failed for Document {$this->documentId}", [
                'error' => $e->getMessage(),
                'url' => $fullUrl ?? 'Unknown'
            ]);
            throw $e; // Throw agar masuk ke method failed() dan di-retry

        } catch (\Exception $e) {
            Log::error("Failed to process document {$this->documentId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $document = Document::find($this->documentId);

        if ($document) {
            $document->update([
                'status' => 'failed',
                'error_message' => 'Gagal setelah beberapa percobaan: ' . $exception->getMessage(),
            ]);
        }

        Log::error("Document {$this->documentId} processing failed permanently", [
            'error' => $exception->getMessage(),
        ]);
    }
}
