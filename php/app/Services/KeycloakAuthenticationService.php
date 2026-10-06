<?php

namespace App\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class KeycloakAuthenticationService
{
    private const PendingSessionKey = 'keycloak.pending';

    public function redirect(Request $request): RedirectResponse
    {
        $configuration = $this->configuration();
        $state = Str::random(64);
        $verifier = Str::random(96);

        $request->session()->put(self::PendingSessionKey, [
            'state' => $state,
            'verifier' => $verifier,
            'expires_at' => now()->addSeconds((int) config('services.keycloak.state_ttl', 300))->getTimestamp(),
        ]);

        return redirect()->away($configuration['authorization_endpoint'].'?'.http_build_query([
            'client_id' => config('services.keycloak.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $this->codeChallenge($verifier),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986));
    }

    /** @return array<string, mixed> */
    public function callback(Request $request): array
    {
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

            $accessToken = $tokenResponse->successful() ? $tokenResponse->json('access_token') : null;
            if (! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException('Keycloak menolak proses login.');
            }

            $profileResponse = Http::acceptJson()
                ->withToken($accessToken)
                ->timeout((int) config('services.keycloak.timeout', 10))
                ->get($configuration['userinfo_endpoint']);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable) {
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
