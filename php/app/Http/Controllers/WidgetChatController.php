<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ApiServiceRunner;
use App\Services\BangkomPegawaiService;
use App\Services\PemetaanPegawaiService;
use App\Services\PortalPegawaiService;
use App\Services\WidgetIdentityVerifier;
use App\Services\WidgetSessionToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetChatController extends ChatController
{
    protected bool $supportsGraphic = false;

    public function __construct(
        PortalPegawaiService $portalPegawaiService,
        PemetaanPegawaiService $pemetaanPegawaiService,
        BangkomPegawaiService $bangkomPegawaiService,
        ApiServiceRunner $apiServiceRunner,
        protected WidgetIdentityVerifier $identityVerifier,
        protected WidgetSessionToken $sessionToken,
    ) {
        parent::__construct($portalPegawaiService, $pemetaanPegawaiService, $bangkomPegawaiService, $apiServiceRunner);
    }

    /**
     * Resolve "user" dari salah satu dari 2 sumber (urutan prioritas):
     *   1. Token sesi login widget sendiri (NIP+password langsung di widget)
     *   2. Token identitas SSO dari situs lain (untuk integrasi masa depan)
     * BUKAN dari sesi login Laravel (karena widget tidak share cookie/sesi
     * dengan chatbot utama - beda origin saat ditanam di situs lain).
     */
    protected function resolveUserModel(Request $request)
    {
        // 1. Coba token sesi login widget (NIP+password via /widget/auth/login)
        $widgetSessionToken = $request->input('widget_session_token');

        if ($widgetSessionToken) {
            $verified = $this->sessionToken->verify($widgetSessionToken);

            if ($verified !== null) {
                return $this->userFromVerifiedNip($verified['nip'], $verified['name']);
            }
        }

        // 2. Fallback: token identitas SSO dari situs lain (kalau nanti dipakai)
        $widgetClient = $request->attributes->get('widget_client');
        $identityToken = $request->input('identity_token');

        if ($widgetClient && $identityToken) {
            $verified = $this->identityVerifier->verify($identityToken, $widgetClient->secret_key);

            if ($verified !== null) {
                return $this->userFromVerifiedNip($verified['nip'], $verified['name']);
            }
        }

        return null;
    }

    protected function userFromVerifiedNip(string $nip, ?string $name)
    {
        // Kalau NIP ini sudah terdaftar sebagai User di sistem kita, pakai
        // langsung modelnya supaya SEMUA fitur data pribadi yang sudah ada
        // (bangkom, pemetaan, dst) berfungsi apa adanya tanpa duplikasi kode.
        $user = User::where('nip', $nip)->first();

        if ($user) {
            return $user;
        }

        // NIP valid tapi belum ada akun User lokal - tetap kembalikan objek
        // ringan supaya fitur berbasis NIP (lookup ke Portal Pegawai dsb)
        // tetap bisa jalan.
        return (object) [
            'id' => null,
            'nip' => $nip,
            'name' => $name,
            'role' => null,
        ];
    }

    /**
     * Widget tidak redirect ke halaman /login (beda situs/origin) - alih-alih
     * kasih flag 'require_widget_login' supaya frontend widget (chat.html)
     * menampilkan pop-up login NIP+password di dalam widget itu sendiri.
     */
    protected function loginRequiredResponse(Request $request, string $userMessage): JsonResponse
    {
        $response = $this->chatResponse(
            $request,
            $userMessage,
            'Untuk menjawab pertanyaan ini, silakan login terlebih dahulu menggunakan NIP dan password Anda.',
            [],
            'unanswered',
        );

        return $response->setData([
            ...$response->getData(true),
            'require_widget_login' => true,
        ]);
    }
}
