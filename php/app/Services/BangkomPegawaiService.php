<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BangkomPegawaiService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            env('BANGKOM_API_KEY', env('BANGKOM_API_BASE_URL', '')),
            '/'
        );
        $this->token = env('BANGKOM_API_TOKEN', '');
    }

    /**
     * Ambil data pengembangan kompetensi pegawai berdasarkan NIP dari API bangkom eksternal.
     * Mengembalikan array asosiatif data bangkom, atau null kalau gagal/tidak ditemukan.
     */
    public function getBangkomByNip(string $nip): ?array
    {
        if (empty($this->baseUrl)) {
            Log::warning('Bangkom Pegawai: endpoint belum diisi di .env');

            return null;
        }

        $result = Cache::remember(
            'bangkom-pegawai:' . $nip,
            now()->addMinutes(5),
            fn (): array => ['data' => $this->requestBangkomByNip($nip)]
        );

        return $result['data'];
    }

    private function requestBangkomByNip(string $nip): ?array
    {
        try {
            $request = Http::timeout(10);

            if (!empty($this->token)) {
                $request = $request->withHeaders([
                    'Authorization' => $this->token,
                ]);
            }

            $response = $request
                ->asJson()
                ->get($this->endpointUrl($nip));

            if (!$response->successful()) {
                Log::warning('Bangkom Pegawai: request data gagal', [
                    'nip' => $nip,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $payload = $response->json();

            if (!is_array($payload) || ($payload['success'] ?? true) !== true) {
                Log::warning('Bangkom Pegawai: payload tidak valid atau success=false', [
                    'nip' => $nip,
                    'payload' => $payload,
                ]);

                return null;
            }

            $data = $payload['data'] ?? null;

            // LOG SEMENTARA UNTUK DEBUG — hapus setelah selesai diagnosa
            Log::info('Bangkom Pegawai: raw response data', [
                'nip' => $nip,
                'keys' => is_array($data) ? array_keys($data) : null,
                'data' => $data,
            ]);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::error('Bangkom Pegawai: exception saat ambil data', [
                'nip' => $nip,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function endpointUrl(string $nip): string
    {
        if (str_contains($this->baseUrl, '?nip=')) {
            return $this->baseUrl . rawurlencode($nip);
        }

        return $this->baseUrl . '/' . rawurlencode($nip) . '/';
    }
}
