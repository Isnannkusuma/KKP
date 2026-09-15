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
            (string) config('services.bangkom_pegawai.base_url', ''),
            '/'
        );
        $this->token = (string) config('services.bangkom_pegawai.token', '');
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

        $cacheKey = 'bangkom-pegawai:'.$nip;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $data = $this->requestBangkomByNip($nip);

        if ($data !== null) {
            Cache::put($cacheKey, $data, now()->addMinutes(5));
        }

        return $data;
    }

    private function requestBangkomByNip(string $nip): ?array
    {
        try {
            $request = Http::connectTimeout(5)
                ->timeout(10)
                ->retry(2, 300, throw: false);

            if (! empty($this->token)) {
                $request = $request->withHeaders([
                    'Authorization' => $this->token,
                ]);
            }

            $response = $request
                ->asJson()
                ->get($this->endpointUrl($nip));

            if (! $response->successful()) {
                Log::warning('Bangkom Pegawai: request data gagal', [
                    'nip' => $nip,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $payload = $response->json();

            if (! is_array($payload) || ($payload['success'] ?? true) !== true) {
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
            return $this->baseUrl.rawurlencode($nip);
        }

        return $this->baseUrl.'/'.rawurlencode($nip).'/';
    }
}
