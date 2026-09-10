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
            env('PEMETAAN_API_KEY', env('PEMETAAN_API_BASE_URL', '')),
            '/'
        );
        $this->token = env('PEMETAAN_API_TOKEN', '');
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

        $result = Cache::remember(
            'pemetaan-pegawai:' . $nip,
            now()->addMinutes(5),
            fn (): array => ['data' => $this->requestPegawaiByNip($nip)]
        );

        return $result['data'];
    }

    private function requestPegawaiByNip(string $nip): ?array
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
                Log::warning('Pemetaan Pegawai: request data gagal', [
                    'nip' => $nip,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $payload = $response->json();

            if (!is_array($payload) || ($payload['success'] ?? true) !== true) {
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
            return $this->baseUrl . rawurlencode($nip);
        }

        return $this->baseUrl . '/' . rawurlencode($nip) . '/';
    }
}