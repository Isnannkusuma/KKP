<?php

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-bot-token',
        'services.python_ai.base_url' => 'http://localhost:8000',
        'ai.providers.openai.key' => null,
        'ai.providers.gemini.key' => 'test-gemini-key',
        'ai.providers.groq.key' => null,
    ]);
});

it('uses the website chat pipeline to answer Telegram questions', function () {
    Http::fake([
        'http://localhost:8000/embed/queries' => Http::response([], 500),
        'http://localhost:8000/search' => Http::response([
            'results' => [[
                'document_id' => 999999,
                'content' => 'Permen KP Nomor 2 Tahun 2025 mengatur fungsi Biro SDMAO.',
                'hybrid_score' => 0.8,
            ]],
        ]),
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'Biro SDMAO menjalankan fungsi sesuai Permen KP tersebut.']],
                ],
                'finishReason' => 'STOP',
            ]],
        ]),
        'https://api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    $this->postJson('/api/webhook/telegram', [
        'message' => [
            'chat' => ['id' => 12345],
            'from' => ['id' => 67890],
            'text' => 'Fungsi Biro SDMAO berdasarkan Permen KP 2 tahun 2025',
        ],
    ])->assertOk()->assertJson(['ok' => true]);

    Http::assertSent(function (ClientRequest $request): bool {
        return $request->url() === 'http://localhost:8000/search'
            && str_contains($request['query'], 'fungsi Biro SDMAO')
            && str_contains($request['query'], '2025');
    });

    Http::assertSent(function (ClientRequest $request): bool {
        return str_starts_with(
            $request->url(),
            'https://generativelanguage.googleapis.com/'
        )
            && str_contains(
                $request['contents'][0]['parts'][0]['text'] ?? '',
                'Permen KP Nomor 2 Tahun 2025 mengatur fungsi Biro SDMAO.'
            );
    });

    Http::assertSent(function (ClientRequest $request): bool {
        return $request->url() === 'https://api.telegram.org/bottest-bot-token/sendMessage'
            && $request['chat_id'] === 12345
            && str_contains(
                $request['text'],
                'Biro SDMAO menjalankan fungsi sesuai Permen KP tersebut.'
            );
    });
});

it('lets the website chat pipeline handle Telegram greetings', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    $this->postJson('/api/webhook/telegram', [
        'message' => [
            'chat' => ['id' => 12345],
            'from' => ['id' => 67890],
            'text' => 'halo',
        ],
    ])->assertOk()->assertJson(['ok' => true]);

    Http::assertSent(function (ClientRequest $request): bool {
        return $request->url() === 'https://api.telegram.org/bottest-bot-token/sendMessage'
            && str_contains($request['text'], 'CS AI Biro SDMAO KKP');
    });
});

it('directs Telegram employment questions to the SSO website without calling the AI pipeline', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true]),
        '*' => Http::response(['error' => 'Unexpected request'], 500),
    ]);

    $this->postJson('/api/webhook/telegram', [
        'message' => [
            'chat' => ['id' => 12345],
            'from' => ['id' => 67890],
            'text' => 'Bagaimana cara melihat data kepegawaian saya?',
        ],
    ])->assertOk()->assertJson(['ok' => true]);

    Http::assertSent(function (ClientRequest $request): bool {
        return $request->url() === 'https://api.telegram.org/bottest-bot-token/sendMessage'
            && str_contains($request['text'], 'https://csai.sdmao-kkp.online/')
            && str_contains($request['text'], 'SSO Kepegawaian');
    });

    Http::assertNotSent(fn (ClientRequest $request): bool => str_contains(
        $request->url(),
        'python-ai'
    ) || str_contains($request->url(), 'generativelanguage.googleapis.com')
        || str_contains($request->url(), 'api.openai.com')
        || str_contains($request->url(), 'api.groq.com'));
});
