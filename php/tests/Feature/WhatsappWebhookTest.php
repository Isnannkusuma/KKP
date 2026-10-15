<?php

use App\Services\Chat\RagChatService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.whatsapp.access_token' => 'test-access-token',
        'services.whatsapp.phone_number_id' => '123456789',
        'services.whatsapp.app_secret' => 'test-app-secret',
        'services.whatsapp.verify_token' => 'test-verify-token',
        'services.whatsapp.api_version' => 'v23.0',
    ]);
});

it('verifies the WhatsApp webhook challenge', function () {
    $this->get('/api/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=test-verify-token&hub.challenge=challenge-123')
        ->assertOk()
        ->assertSeeText('challenge-123');
});

it('rejects a webhook with an invalid verify token', function () {
    $this->get('/api/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=challenge-123')
        ->assertForbidden();
});

it('sends incoming text to the AI service and replies through WhatsApp', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.reply']]]),
    ]);

    $ragChatService = Mockery::mock(RagChatService::class);
    $ragChatService->shouldReceive('process')
        ->once()
        ->with('Apa kabar?')
        ->andReturn('Halo, ada yang bisa dibantu?');
    app()->instance(RagChatService::class, $ragChatService);

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from' => '6285150000000',
                        'type' => 'text',
                        'text' => ['body' => 'Apa kabar?'],
                    ]],
                ],
            ]],
        ]],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');

    $this->withHeader('X-Hub-Signature-256', $signature)
        ->postJson('/api/webhook/whatsapp', $payload)
        ->assertOk()
        ->assertJson(['ok' => true]);

    Http::assertSent(function (ClientRequest $request) {
        return $request->url() === 'https://graph.facebook.com/v23.0/123456789/messages'
            && $request->hasHeader('Authorization', 'Bearer test-access-token')
            && $request['to'] === '6285150000000'
            && $request['text']['body'] === 'Halo, ada yang bisa dibantu?';
    });
});

it('rejects incoming webhook requests with an invalid signature', function () {
    Http::fake();

    $response = $this->withHeader('X-Hub-Signature-256', 'sha256=invalid')
        ->postJson('/api/webhook/whatsapp', ['entry' => []]);

    $response->assertForbidden();
    Http::assertNothingSent();
});
