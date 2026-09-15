<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PemetaanPegawaiService
{
    protected string $baseUrl;

    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            (string) config('services.pemetaan_pegawai.base_url', ''),
            '/'
        );
        $this->token = (string) config('services.pemetaan_pegawai.token', '');
    }

    /**
     * Ambil data 1 pegawai berdasarkan NIP dari API pemetaan eksternal.
     * Mengembalikan array asosiatif data pegawai, atau null kalau gagal/tidak ditemukan.
     */
    public function getPegawaiByNip(string $nip): ?array
    {
        if (empty($this->baseUrl)) {
            Log::warning('Pemetaan Pegawai: endpoint belum diisi di .env');

            return null;
        }

        $cacheKey = 'pemetaan-pegawai:'.$nip;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $data = $this->requestPegawaiByNip($nip);

        if ($data !== null) {
            Cache::put($cacheKey, $data, now()->addMinutes(5));
        }

        return $data;
    }

    private function requestPegawaiByNip(string $nip): ?array
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
                Log::warning('Pemetaan Pegawai: request data gagal', [
                    'nip' => $nip,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $payload = $response->json();

            if (! is_array($payload) || ($payload['success'] ?? true) !== true) {
                return null;
            }

            $data = $payload['data'] ?? null;

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::error('Pemetaan Pegawai: exception saat ambil data', [
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
