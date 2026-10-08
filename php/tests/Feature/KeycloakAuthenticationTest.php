<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::table('users', function (Blueprint $table): void {
        $table->unsignedBigInteger('id_roles')->nullable();
    });

    config()->set('services.keycloak', [
        'enabled' => true,
        'issuer' => 'https://keycloak.example.test/realms/kkp',
        'client_id' => 'csai-sdmao',
        'client_secret' => 'test-secret',
        'redirect_uri' => 'https://csai.example.test/login',
        'timeout' => 10,
        'state_ttl' => 300,
        'pegawai_role_id' => 19,
    ]);
});

test('keycloak login redirects using the configured callback URL', function (): void {
    Http::fake([
        'https://keycloak.example.test/realms/kkp/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => 'https://keycloak.example.test/authorize',
            'token_endpoint' => 'https://keycloak.example.test/token',
            'userinfo_endpoint' => 'https://keycloak.example.test/userinfo',
        ]),
    ]);

    $response = $this->get('https://csai.example.test/login/keycloak');

    $response->assertRedirectContains('https://keycloak.example.test/authorize')
        ->assertRedirectContains('redirect_uri=https%3A%2F%2Fcsai.example.test%2Flogin')
        ->assertRedirectContains('code_challenge_method=S256');
});

test('keycloak login canonicalizes the host before starting the SSO session', function (): void {
    $response = $this->get('https://csai.sdmao-kkp.example.test/login/keycloak');

    $response->assertRedirect('https://csai.example.test/login/keycloak');
});

test('keycloak login redirects employee users directly to chat', function (): void {
    $user = User::factory()->create([
        'nip' => '198001012006041001',
        'id_roles' => 19,
    ]);

    Http::fake([
        'https://keycloak.example.test/realms/kkp/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => 'https://keycloak.example.test/authorize',
            'token_endpoint' => 'https://keycloak.example.test/token',
            'userinfo_endpoint' => 'https://keycloak.example.test/userinfo',
        ]),
        'https://keycloak.example.test/token' => Http::response(['access_token' => 'access-token']),
        'https://keycloak.example.test/userinfo' => Http::response(['preferred_username' => $user->nip]),
    ]);

    $response = $this->withSession([
        'url.intended' => '/login',
        'keycloak.pending' => [
            'state' => 'state-123',
            'verifier' => str_repeat('v', 96),
            'expires_at' => now()->addMinute()->getTimestamp(),
        ],
    ])->get('/login?code=authorization-code&state=state-123');

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('chat.index', absolute: false));
});

test('keycloak rejects users without employee role 19', function (): void {
    $user = User::factory()->create([
        'nip' => '198001012006041001',
        'id_roles' => 20,
    ]);

    Http::fake([
        'https://keycloak.example.test/realms/kkp/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => 'https://keycloak.example.test/authorize',
            'token_endpoint' => 'https://keycloak.example.test/token',
            'userinfo_endpoint' => 'https://keycloak.example.test/userinfo',
        ]),
        'https://keycloak.example.test/token' => Http::response(['access_token' => 'access-token']),
        'https://keycloak.example.test/userinfo' => Http::response(['preferred_username' => $user->nip]),
    ]);

    $response = $this->withSession([
        'keycloak.pending' => [
            'state' => 'state-123',
            'verifier' => str_repeat('v', 96),
            'expires_at' => now()->addMinute()->getTimestamp(),
        ],
    ])->get('/login?code=authorization-code&state=state-123');

    $this->assertGuest();
    $response->assertRedirect(route('login', absolute: false));
    $response->assertSessionHasErrors('login');
});
