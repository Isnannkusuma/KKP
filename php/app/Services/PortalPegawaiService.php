<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PortalPegawaiService
{
    protected string $baseUrl;

    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.portal_pegawai.base_url', ''), '/');
        $this->token = (string) config('services.portal_pegawai.token', '');
    }

    /**
     * Ambil data 1 pegawai berdasarkan NIP dari API portal eksternal.
     * Mengembalikan array asosiatif data pegawai, atau null kalau gagal/tidak ditemukan.
     */
    public function getPegawaiByNip(string $nip): ?array
    {
        if (empty($this->token)) {
            Log::warning('Portal Pegawai: PORTAL_API_TOKEN belum diisi di .env');

            return null;
        }

        $cacheKey = 'portal-pegawai:'.$nip;

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
            Log::info('Portal Pegawai: request dimulai', [
                'nip' => $nip,
                'endpoint' => $this->endpointUrl($nip),
            ]);

            $response = Http::connectTimeout(5)
                ->timeout(10)
                ->retry(2, 300, throw: false)
                ->withHeaders([
                    'Authorization' => $this->token,
                ])
                ->asJson()
                ->get($this->endpointUrl($nip));

            if (! $response->successful()) {
                Log::warning('Portal Pegawai: request data gagal', [
                    'nip' => $nip,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            $records = $data['result'] ?? $data['data'] ?? $data;

            if (! is_array($records)) {
                return null;
            }

            if (array_is_list($records)) {
                foreach ($records as $record) {
                    if (is_array($record) && (string) ($record['nip'] ?? '') === $nip) {
                        Log::info('Portal Pegawai: request berhasil', [
                            'nip' => $nip,
                            'status' => $response->status(),
                            'data_found' => true,
                        ]);

                        return $record;
                    }
                }

                Log::info('Portal Pegawai: request berhasil', [
                    'nip' => $nip,
                    'status' => $response->status(),
                    'data_found' => false,
                ]);

                return null;
            }

            Log::info('Portal Pegawai: request berhasil', [
                'nip' => $nip,
                'status' => $response->status(),
                'data_found' => true,
            ]);

            return $records;
        } catch (\Throwable $e) {
            Log::error('Portal Pegawai: exception saat ambil data', [
                'nip' => $nip,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function endpointUrl(string $nip): string
    {
        return $this->baseUrl.'/'.rawurlencode($nip).'/';
    }
}
