<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PortalPegawaiService;
use App\Services\WidgetSessionToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class WidgetAuthController extends Controller
{
    public function __construct(
        protected WidgetSessionToken $sessionToken,
        protected PortalPegawaiService $portalPegawaiService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nip' => 'required|string',
            'password' => 'required|string',
        ]);

        $nip = trim($validated['nip']);
        $password = $validated['password'];

        $user = $this->authenticate($nip, $password);

        if (! $user) {
            return response()->json([
                'message' => 'NIP atau password salah.',
            ], 422);
        }

        $token = $this->sessionToken->issue($user->nip, $user->name);

        return response()->json([
            'token' => $token,
            'user' => [
                'name' => $user->name,
                'nip' => $user->nip,
            ],
        ]);
    }

    /**
     * Stateless - token cukup dihapus di sisi client (localStorage/memori).
     * Endpoint ini disediakan untuk konsistensi & kemungkinan pengembangan
     * blacklist token di masa depan.
     */
    public function logout(): JsonResponse
    {
        return response()->json(['message' => 'Berhasil logout.']);
    }

    /**
     * Logika autentikasi sama seperti Fortify::authenticateUsing di
     * FortifyServiceProvider, agar login widget (NIP+password) juga
     * mendukung fallback legacy (password default = NIP) dan
     * pembuatan akun otomatis dari data pegawai.
     */
    private function authenticate(string $nip, string $password): ?User
    {
        if ($nip === '' || $password === '') {
            return null;
        }

        $user = User::where('nip', $nip)->first();

        if ($user && Hash::check($password, $user->password)) {
            return $user;
        }

        // Legacy fallback: password default adalah NIP untuk user yang belum
        // mempunyai akun Laravel atau masih memakai pola lama.
        if (! $user && $password !== $nip) {
            return null;
        }

        if ($user) {
            return null;
        }

        $pegawai = DB::connection('pegawai')
            ->table('m_peg')
            ->where('nip', $nip)
            ->first();

        $portalData = null;

        if (! $pegawai) {
            $portalData = $this->portalPegawaiService->getPegawaiByNip($nip);

            if (! $portalData) {
                Log::warning('Login widget gagal: NIP tidak ditemukan lokal maupun portal', [
                    'nip' => $nip,
                ]);

                return null;
            }
        }

        $pegawaiData = $pegawai ? (array) $pegawai : $portalData;

        return User::create([
            'name' => $pegawaiData['nama'] ?? 'Pegawai',
            'nip' => $pegawaiData['nip'] ?? $nip,
            'email' => $nip.'@pegawai.local',
            'password' => Hash::make($nip),
            'role' => 'user',
        ]);
    }
}
