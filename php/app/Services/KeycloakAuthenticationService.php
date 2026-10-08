<?php

namespace App\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class KeycloakAuthenticationService
{
    private const PendingSessionKey = 'keycloak.pending';

    private const IdTokenSessionKey = 'keycloak.id_token';

    public function redirect(Request $request): RedirectResponse
    {
        $callback = parse_url($this->redirectUri());
        $scheme = $callback['scheme'] ?? null;
        $host = $callback['host'] ?? null;

        if (! is_string($scheme) || ! is_string($host)) {
            throw new RuntimeException('URL callback Keycloak tidak valid.');
        }

        if (strcasecmp($request->getScheme(), $scheme) !== 0
            || strcasecmp($request->getHost(), $host) !== 0
            || (isset($callback['port']) && $request->getPort() !== $callback['port'])) {
            $origin = $scheme.'://'.$host;
            if (isset($callback['port'])) {
                $origin .= ':'.$callback['port'];
            }

            return redirect()->away($origin.$request->getPathInfo());
        }

        $configuration = $this->configuration();
        $state = Str::random(64);
        $verifier = Str::random(96);

        $request->session()->put(self::PendingSessionKey, [
            'state' => $state,
            'verifier' => $verifier,
            'expires_at' => now()->addSeconds((int) config('services.keycloak.state_ttl', 300))->getTimestamp(),
        ]);

        Log::info('KEYCLOAK redirect', [
            'session_id' => substr($request->session()->getId(), 0, 8),
            'host' => $request->getHost(),
            'redirect_uri' => $this->redirectUri(),
        ]);

        return redirect()->away($configuration['authorization_endpoint'].'?'.http_build_query([
            'client_id' => config('services.keycloak.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $this->codeChallenge($verifier),
            'code_challenge_method' => 'S256',
            // Selalu minta kredensial, supaya bisa ganti akun setelah logout
            'prompt' => 'login',
        ], '', '&', PHP_QUERY_RFC3986));
    }

    /** @return array<string, mixed> */
    public function callback(Request $request): array
    {
        $peek = $request->session()->get(self::PendingSessionKey);
        Log::info('KEYCLOAK state check', [
            'session_id' => substr($request->session()->getId(), 0, 8),
            'host' => $request->getHost(),
            'has_session_cookie' => $request->cookies->has(config('session.cookie')),
            'pending_ada' => is_array($peek),
            'state_cocok' => is_array($peek)
                && hash_equals((string) ($peek['state'] ?? ''), (string) $request->query('state')),
        ]);

        $pending = $request->session()->pull(self::PendingSessionKey);
        $state = $request->query('state');
        $code = $request->query('code');

        if (! is_array($pending)
            || ! is_string($state)
            || ! is_string($code)
            || ! isset($pending['state'], $pending['verifier'], $pending['expires_at'])
            || ! hash_equals((string) $pending['state'], $state)
            || (int) $pending['expires_at'] < now()->getTimestamp()) {
            throw new RuntimeException('Sesi login telah kedaluwarsa. Silakan coba lagi.');
        }

        $configuration = $this->configuration();

        try {
            $tokenResponse = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.keycloak.timeout', 10))
                ->post($configuration['token_endpoint'], [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('services.keycloak.client_id'),
                    'client_secret' => config('services.keycloak.client_secret'),
                    'redirect_uri' => $this->redirectUri(),
                    'code' => $code,
                    'code_verifier' => $pending['verifier'],
                ]);

            if (! $tokenResponse->successful()) {
                Log::warning('KEYCLOAK token gagal', [
                    'status' => $tokenResponse->status(),
                    'error' => $tokenResponse->json('error'),
                    'description' => $tokenResponse->json('error_description'),
                ]);
            }

            $accessToken = $tokenResponse->successful() ? $tokenResponse->json('access_token') : null;
            if (! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException('Keycloak menolak proses login.');
            }

            // Simpan id_token untuk id_token_hint saat logout
            $idToken = $tokenResponse->json('id_token');
            if (is_string($idToken) && $idToken !== '') {
                $request->session()->put(self::IdTokenSessionKey, $idToken);
            }

            $profileResponse = Http::acceptJson()
                ->withToken($accessToken)
                ->timeout((int) config('services.keycloak.timeout', 10))
                ->get($configuration['userinfo_endpoint']);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('KEYCLOAK HTTP error', ['msg' => $exception->getMessage()]);

            throw new RuntimeException('Layanan Keycloak tidak dapat dihubungi.');
        }

        if (! $profileResponse->successful() || ! is_array($profileResponse->json())) {
            throw new RuntimeException('Profil Keycloak tidak dapat diverifikasi.');
        }

        return $profileResponse->json();
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.keycloak.enabled')
            && filter_var(config('services.keycloak.issuer'), FILTER_VALIDATE_URL)
            && (string) config('services.keycloak.client_id') !== ''
            && (string) config('services.keycloak.client_secret') !== ''
            && filter_var($this->redirectUri(), FILTER_VALIDATE_URL);
    }

    /**
     * URL logout Keycloak. Panggil SEBELUM session Laravel di-invalidate,
     * karena id_token diambil dari session.
     */
    public function logoutUrl(?Request $request = null): ?string
    {
        try {
            $configuration = $this->configuration();
        } catch (RuntimeException) {
            return null;
        }

        $endpoint = $configuration['end_session_endpoint'] ?? null;

        if (! is_string($endpoint) || $endpoint === '') {
            return null;
        }

        // Pakai host yang sama dengan redirect_uri (tempat session berada)
        $parts = parse_url($this->redirectUri());
        $base = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $base .= ':'.$parts['port'];
        }

        $params = [
            'client_id' => config('services.keycloak.client_id'),
            'post_logout_redirect_uri' => $base.'/chat',
        ];

        $idToken = $request?->session()->get(self::IdTokenSessionKey);
        if (is_string($idToken) && $idToken !== '') {
            $params['id_token_hint'] = $idToken;
        }

        return $endpoint.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<string, string> */
    private function configuration(): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Login SSO Keycloak belum dikonfigurasi.');
        }

        $issuer = rtrim((string) config('services.keycloak.issuer'), '/');
        $cacheKey = 'keycloak.configuration.'.hash('sha256', $issuer);

        try {
            $configuration = Cache::remember($cacheKey, now()->addHour(), function () use ($issuer): array {
                $response = Http::acceptJson()
                    ->timeout((int) config('services.keycloak.timeout', 10))
                    ->get($issuer.'/.well-known/openid-configuration');

                return $response->successful() && is_array($response->json()) ? $response->json() : [];
            });
        } catch (\Throwable) {
            throw new RuntimeException('Konfigurasi Keycloak tidak dapat dimuat.');
        }

        foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $endpoint) {
            if (! isset($configuration[$endpoint]) || ! filter_var($configuration[$endpoint], FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Konfigurasi Keycloak tidak valid.');
            }
        }

        return $configuration;
    }

    private function redirectUri(): string
    {
        return (string) config('services.keycloak.redirect_uri');
    }

    private function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
