<?php

use App\Models\ApiService;
use App\Models\User;
use App\Services\ApiServiceRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('only admins can access api service management', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)
        ->get(route('api-services.index'))
        ->assertForbidden();
});

test('admin can create an api service with encrypted secrets', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->withoutMiddleware()
        ->actingAs($admin)
        ->post(route('api-services.store'), [
            'name' => 'Portal Pegawai',
            'description' => 'Data pegawai',
            'method' => 'GET',
            'url' => 'https://example.com/pegawai/{{nip}}',
            'headers' => ['Authorization' => 'secret-token'],
            'query_params' => ['format' => 'json'],
            'body' => [],
            'request_schema' => [[
                'name' => 'nip',
                'type' => 'string',
                'required' => true,
            ]],
            'response_fields' => [[
                'name' => 'nama',
                'path' => 'data.nama',
            ]],
            'is_active' => true,
        ])
        ->assertRedirect(route('api-services.index'));

    $service = ApiService::firstOrFail();
    expect($service->headers)->toBe(['Authorization' => 'secret-token']);

    expect($service->request_schema[0]['name'])->toBe('nip')
        ->and($service->response_fields[0]['path'])->toBe('data.nama');
});

test('api service runner can call multiple services in one combined request', function () {
    Http::fake([
        'https://example.com/pegawai/*' => Http::response(['data' => ['nama' => 'Budi']], 200),
        'https://example.com/kehadiran/*' => Http::response(['data' => ['hadir' => 21]], 200),
        'https://example.com/rekomendasi/*' => Http::response(['data' => ['rekomendasi' => 'Pelatihan teknis']], 200),
    ]);

    $services = [
        ApiService::create([
            'name' => 'Profil pegawai',
            'description' => 'Data profil pegawai',
            'method' => 'GET',
            'url' => 'https://example.com/pegawai/{{nip}}',
            'headers' => [],
            'query_params' => [],
            'body' => [],
            'request_schema' => [['name' => 'nip', 'type' => 'string', 'required' => true]],
            'response_fields' => [['name' => 'nama', 'path' => 'data.nama']],
            'is_active' => true,
        ]),
        ApiService::create([
            'name' => 'Kehadiran pegawai',
            'description' => 'Data kehadiran pegawai',
            'method' => 'GET',
            'url' => 'https://example.com/kehadiran/{{nip}}',
            'headers' => [],
            'query_params' => [],
            'body' => [],
            'request_schema' => [['name' => 'nip', 'type' => 'string', 'required' => true]],
            'response_fields' => [['name' => 'hadir', 'path' => 'data.hadir']],
            'is_active' => true,
        ]),
        ApiService::create([
            'name' => 'Rekomendasi pelatihan',
            'description' => 'Data rekomendasi training',
            'method' => 'GET',
            'url' => 'https://example.com/rekomendasi/{{nip}}',
            'headers' => [],
            'query_params' => [],
            'body' => [],
            'request_schema' => [['name' => 'nip', 'type' => 'string', 'required' => true]],
            'response_fields' => [['name' => 'rekomendasi', 'path' => 'data.rekomendasi']],
            'is_active' => true,
        ]),
    ];

    $results = app(ApiServiceRunner::class)->runMany($services, ['nip' => '19880101']);

    expect($results)->toHaveCount(3)
        ->and($results[0]['success'])->toBeTrue()
        ->and($results[0]['selected'])->toMatchArray(['nama' => 'Budi'])
        ->and($results[1]['selected'])->toMatchArray(['hadir' => 21])
        ->and($results[2]['selected'])->toMatchArray(['rekomendasi' => 'Pelatihan teknis']);
});
