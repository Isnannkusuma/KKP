<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\KeycloakAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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
        if ($request->filled('error')) {
            return redirect()->route('login')->withErrors([
                'login' => 'Login Keycloak dibatalkan atau ditolak.',
            ]);
        }

        try {
            $identity = $this->keycloak->callback($request);
            $user = $this->pegawaiUser($identity);
        } catch (RuntimeException $exception) {
            return redirect()->route('login')->withErrors(['login' => $exception->getMessage()]);
        }

        if ($user === null) {
            return redirect()->route('login')->withErrors([
                'login' => 'Akun SSO Anda belum terdaftar sebagai pegawai CSAI.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('chat.index'));
    }

    /** @param array<string, mixed> $identity */
    private function pegawaiUser(array $identity): ?User
    {
        if (! Schema::hasColumn('users', 'id_roles')) {
            throw new RuntimeException('Kolom peran pengguna belum tersedia.');
        }

        $nip = $this->claim($identity, ['nip', 'preferred_username', 'username']);
        if ($nip === null || ! preg_match('/^[0-9]{8,32}$/', $nip)) {
            throw new RuntimeException('NIP dari Keycloak tidak valid.');
        }

        return User::query()
            ->where('nip', $nip)
            ->where('id_roles', (int) config('services.keycloak.pegawai_role_id', 19))
            ->first();
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
