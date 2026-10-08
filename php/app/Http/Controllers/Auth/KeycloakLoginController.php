<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\KeycloakAuthenticationService;
use App\Services\PortalPegawaiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class KeycloakLoginController extends Controller
{
    public function __construct(public KeycloakAuthenticationService $keycloak) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->keycloak->isConfigured()) {
            return redirect()->route('login')->withErrors([
                'login' => 'Login SSO Keycloak belum dikonfigurasi.',
            ]);
        }

        return $this->keycloak->redirect($request);
    }

    public function callback(Request $request): RedirectResponse
    {
        Log::info('KEYCLOAK callback dipanggil', [
            'query_keys' => array_keys($request->query()),
            'has_session' => $request->hasSession(),
            'error' => $request->query('error'),
        ]);

        if ($request->filled('error')) {
            Log::warning('KEYCLOAK ditolak', [
                'error' => $request->query('error'),
                'description' => $request->query('error_description'),
            ]);

            return redirect()->route('login')->withErrors([
                'login' => 'Login Keycloak dibatalkan atau ditolak.',
            ]);
        }

        try {
            $identity = $this->keycloak->callback($request);

            Log::info('KEYCLOAK identity', [
                'keys' => array_keys($identity),
                'nip' => $identity['nip']
                    ?? $identity['preferred_username']
                    ?? $identity['username']
                    ?? null,
            ]);

            $user = $this->pegawaiUser($identity);
        } catch (RuntimeException $exception) {
            Log::warning('KEYCLOAK gagal', ['msg' => $exception->getMessage()]);

            return redirect()->route('login')->withErrors(['login' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            // Error tak terduga (HTTP, token, session, dll.) supaya tercatat di log
            Log::error('KEYCLOAK error tak terduga', [
                'class' => $exception::class,
                'msg' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);

            return redirect()->route('login')->withErrors([
                'login' => 'Terjadi kesalahan saat login SSO.',
            ]);
        }

        Log::info('KEYCLOAK user', ['found' => $user !== null, 'id' => $user?->id]);

        if ($user === null) {
            return redirect()->route('login')->withErrors([
                'login' => 'Akun SSO Anda belum terdaftar sebagai pegawai CSAI.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        Log::info('KEYCLOAK login berhasil', [
            'user_id' => $user->id,
            'auth_check' => Auth::check(),
        ]);

        return redirect()->route('chat.index');
    }

    /** @param array<string, mixed> $identity */
    private function pegawaiUser(array $identity): ?User
    {
        if (! Schema::hasColumn('users', 'role')) {
            throw new RuntimeException('Kolom peran pengguna belum tersedia.');
        }

        $nip = $this->claim($identity, ['nip', 'preferred_username', 'username']);
        if ($nip === null || ! preg_match('/^[0-9]{8,32}$/', $nip)) {
            Log::warning('KEYCLOAK NIP tidak valid');

            throw new RuntimeException('NIP dari Keycloak tidak valid.');
        }

        $user = User::query()->where('nip', $nip)->first();

        if ($user !== null) {
            return $user;
        }

        // Belum ada di tabel users: validasi NIP ke Portal Pegawai lalu buat akun otomatis
        $pegawai = app(PortalPegawaiService::class)->getPegawaiByNip($nip);

        if ($pegawai === null) {
            Log::warning('KEYCLOAK NIP tidak ditemukan di users maupun Portal Pegawai');

            return null;
        }

        return User::create([
            'name' => $pegawai['nama'] ?? $identity['name'] ?? 'Pegawai',
            'nip' => $nip,
            'email' => $nip.'@pegawai.local',
            'password' => Str::random(40),
            'role' => 'user',
        ]);
    }

    /** @param array<string, mixed> $identity */
    private function claim(array $identity, array $names): ?string
    {
        foreach ($names as $name) {
            $value = $identity[$name] ?? null;
            if (is_string($value) && ($value = trim($value)) !== '') {
                return $value;
            }
        }

        return null;
    }
}
