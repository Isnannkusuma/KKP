<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Auth\KeycloakLoginController;
use App\Models\User;
use App\Services\PortalPegawaiService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponse::class, function (): LoginResponse {
            return new class implements LoginResponse
            {
                public function toResponse($request)
                {
                    return redirect()->intended(
                        $request->user()->role === 'admin'
                            ? route('dashboard')
                            : route('chat.index')
                    );
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureAuthentication();
    }

    /**
     * Configure Fortify authentication.
     */
    private function configureAuthentication(): void
    {
        Fortify::username('nip');

        Fortify::authenticateUsing(function (Request $request) {
            $nip = trim($request->input('nip', ''));
            $password = $request->input('password', '');

            // NIP dan password wajib diisi
            if (empty($nip) || empty($password)) {
                return null;
            }

            /*
             * Cari pegawai berdasarkan NIP
             * Database: siantar
             * Connection: pegawai
             * Table: m_peg
             */
            $pegawai = DB::connection('pegawai')
                ->table('m_peg')
                ->where('nip', $nip)
                ->first();

            $user = User::where('nip', $nip)->first();

            if ($user && Hash::check($password, $user->password)) {
                return $user;
            }

            /*
             * Legacy fallback: password default adalah NIP untuk user yang belum
             * mempunyai akun Laravel atau masih memakai pola lama.
             */
            if (! $user && $password !== $nip) {
                return null;
            }

            $portalData = null;

            if (! $pegawai) {
                $portalData = app(PortalPegawaiService::class)->getPegawaiByNip($nip);

                if (! $portalData) {
                    Log::warning('Login pegawai gagal: NIP tidak ditemukan lokal maupun portal', [
                        'nip' => $nip,
                        'portal_api_called' => true,
                    ]);

                    return null;
                }
            }

            $pegawaiData = $pegawai
                ? (array) $pegawai
                : $portalData;

            Log::info('Login pegawai berhasil', [
                'nip' => $nip,
                'data_source' => $pegawai ? 'database lokal' : 'Portal Pegawai',
                'portal_api_called' => (bool) $portalData,
            ]);

            /*
             * Cari akun Laravel berdasarkan NIP.
             */
            $user = $user ?: User::where('nip', $nip)->first();

            /*
             * Jika belum mempunyai akun Laravel,
             * buat akun secara otomatis.
             */
            if (! $user) {
                $user = User::create([
                    'name' => $pegawaiData['nama'] ?? 'Pegawai',
                    'nip' => $pegawaiData['nip'] ?? $nip,
                    'email' => $nip.'@pegawai.local',
                    'password' => Hash::make($nip),
                    'role' => 'user',
                ]);
            }

            /*
             * Jika akun sudah ada, jangan mengubah
             * password atau role yang sudah tersimpan.
             */
            return $user;
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::username('nip');

        Fortify::resetUserPasswordsUsing(
            ResetUserPassword::class
        );
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            if ($request->hasAny(['code', 'state', 'error'])) {
                return app(KeycloakLoginController::class)->callback($request);
            }

            return Inertia::render('auth/login', [
                'canResetPassword' => Features::enabled(
                    Features::resetPasswords()
                ),
                'keycloakEnabled' => app(KeycloakLoginController::class)->keycloak->isConfigured(),
                'keycloakLoginUrl' => route('keycloak.login'),
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::resetPasswordView(function (Request $request) {
            return Inertia::render('auth/reset-password', [
                'email' => $request->email,
                'token' => $request->route('token'),
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
            ]);
        });

        Fortify::requestPasswordResetLinkView(function (Request $request) {
            return Inertia::render('auth/forgot-password', [
                'status' => $request->session()->get('status'),
            ]);
        });
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower(
                    $request->input(Fortify::username())
                ).'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
