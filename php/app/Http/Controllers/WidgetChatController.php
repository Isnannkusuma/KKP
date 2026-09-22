<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WidgetIdentityVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetChatController extends ChatController
{
    public function __construct(
        \App\Services\PortalPegawaiService $portalPegawaiService,
        \App\Services\PemetaanPegawaiService $pemetaanPegawaiService,
        \App\Services\BangkomPegawaiService $bangkomPegawaiService,
        \App\Services\ApiServiceRunner $apiServiceRunner,
        protected WidgetIdentityVerifier $identityVerifier,
    ) {
        parent::__construct($portalPegawaiService, $pemetaanPegawaiService, $bangkomPegawaiService, $apiServiceRunner);
    }

    /**
     * Resolve "user" dari token identitas yang dikirim situs lain (SSO),
     * BUKAN dari sesi login Laravel (karena widget tidak share cookie/sesi
     * dengan chatbot utama - beda origin).
     */
    protected function resolveUserModel(Request $request)
    {
        $widgetClient = $request->attributes->get('widget_client');
        $identityToken = $request->input('identity_token');

        if (! $widgetClient || ! $identityToken) {
            return null;
        }

        $verified = $this->identityVerifier->verify($identityToken, $widgetClient->secret_key);

        if ($verified === null) {
            return null;
        }

        // Kalau NIP ini sudah terdaftar sebagai User di sistem kita, pakai
        // langsung modelnya supaya SEMUA fitur data pribadi yang sudah ada
        // (bangkom, pemetaan, dst) berfungsi apa adanya tanpa duplikasi kode.
        $user = User::where('nip', $verified['nip'])->first();

        if ($user) {
            return $user;
        }

        // NIP valid (lolos verifikasi tanda tangan) tapi belum ada akun User
        // lokal - tetap kembalikan objek ringan supaya fitur berbasis NIP
        // (lookup ke Portal Pegawai dsb) tetap bisa jalan.
        return (object) [
            'id' => null,
            'nip' => $verified['nip'],
            'name' => $verified['name'],
            'role' => null,
        ];
    }

    /**
     * Widget tidak punya konsep redirect ke halaman /login (beda situs/origin),
     * jadi cukup kasih pesan yang jelas ke pengguna.
     */
    protected function loginRequiredResponse(Request $request, string $userMessage): JsonResponse
    {
        return $this->chatResponse(
            $request,
            $userMessage,
            'Untuk pertanyaan terkait data pribadi, silakan login terlebih dahulu di situs ini.',
            [],
            'unanswered',
        );
    }
}