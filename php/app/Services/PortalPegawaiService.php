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
        $this->baseUrl = rtrim(env('PORTAL_API_BASE_URL', ''), '/');
        $this->token = env('PORTAL_API_TOKEN', '');
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

        $result = Cache::remember(
            'portal-pegawai:' . $nip,
            now()->addMinutes(5),
            fn (): array => ['data' => $this->requestPegawaiByNip($nip)]
        );

        return $result['data'];
    }

    private function requestPegawaiByNip(string $nip): ?array
    {
        try {
            Log::info('Portal Pegawai: request dimulai', [
                'nip' => $nip,
                'endpoint' => $this->endpointUrl($nip),
            ]);

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => $this->token,
                ])
                ->asJson()
                ->get($this->endpointUrl($nip));

            if (!$response->successful()) {
                Log::warning('Portal Pegawai: request data gagal', [
                    'nip' => $nip,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            $records = $data['result'] ?? $data['data'] ?? $data;

            if (!is_array($records)) {
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
        return $this->baseUrl . '/' . rawurlencode($nip) . '/';
    }
}