<?php

use App\Models\SearchAuditLog;
use App\Models\WidgetClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registered dashboard widget receives only aggregate monitoring data', function () {
    $widget = WidgetClient::create([
        'name' => 'Portal SDMAO',
        'widget_key' => 'wk_dashboard_test',
        'secret_key' => str_repeat('s', 48),
        'allowed_origins' => ['https://portal.example.test'],
        'is_active' => true,
        'dashboard_enabled' => true,
    ]);

    SearchAuditLog::create([
        'user_type' => 'guest',
        'user_identifier' => 'private-user-identifier',
        'query' => 'private question text',
        'answer' => 'private answer text',
        'response_status' => 'answered',
        'result_count' => 1,
        'access_level_used' => 'public',
    ]);
    SearchAuditLog::create([
        'user_type' => 'guest',
        'user_identifier' => 'another-private-identifier',
        'query' => 'another private question',
        'response_status' => 'unanswered',
        'result_count' => 0,
        'access_level_used' => 'public',
    ]);

    $response = $this->withHeader('Origin', 'https://portal.example.test')
        ->getJson('/api/widget/dashboard?period=7&widget_key='.$widget->widget_key);

    $response->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://portal.example.test')
        ->assertJsonPath('period', 7)
        ->assertJsonPath('stats.total', 2)
        ->assertJsonPath('stats.answered', 1)
        ->assertJsonPath('stats.unanswered', 1)
        ->assertJsonCount(7, 'daily')
        ->assertJsonMissingPath('data')
        ->assertJsonMissingPath('stats.questions');

    expect($response->getContent())
        ->not->toContain('private-user-identifier')
        ->not->toContain('private question text')
        ->not->toContain('private answer text');
});

test('dashboard endpoint rejects unregistered origins and clients without dashboard access', function () {
    WidgetClient::create([
        'name' => 'Chat only',
        'widget_key' => 'wk_chat_only_test',
        'secret_key' => str_repeat('s', 48),
        'allowed_origins' => ['https://portal.example.test'],
        'is_active' => true,
        'dashboard_enabled' => false,
    ]);

    $this->withHeader('Origin', 'https://portal.example.test')
        ->getJson('/api/widget/dashboard?widget_key=wk_chat_only_test')
        ->assertNotFound();

    $this->withHeader('Origin', 'https://unregistered.example.test')
        ->getJson('/api/widget/dashboard?widget_key=wk_chat_only_test')
        ->assertForbidden();
});
