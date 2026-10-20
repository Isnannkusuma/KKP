<?php

use App\Services\Channels\TelegramService;
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

it('answers public list questions even when they include the word pegawai', function () {
    $results = collect(range(1, 10))
        ->map(fn (int $number): array => [
            'document_id' => 999999,
            'content' => "Nama: Pegawai {$number}\nTanggal pelantikan: 16 Oktober 2026",
            'hybrid_score' => 0.8,
        ])
        ->all();

    Http::fake([
        'http://localhost:8000/search' => Http::response(['results' => $results]),
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => "1. Pegawai 1\n2. Pegawai 2"]],
                ],
                'finishReason' => 'STOP',
            ]],
        ]),
        'https://api.telegram.org/*' => Http::response(['ok' => true]),
        '*' => Http::response(['error' => 'Unexpected request'], 500),
    ]);

    $this->postJson('/api/webhook/telegram', [
        'message' => [
            'chat' => ['id' => 12345],
            'from' => ['id' => 67890],
            'text' => 'berikan 10 nama pegawai yang dilantik pada hari ini',
        ],
    ])->assertOk()->assertJson(['ok' => true]);

    Http::assertSent(function (ClientRequest $request): bool {
        return $request->url() === 'http://localhost:8000/search'
            && $request['top_k'] === 50
            && ! str_contains(mb_strtolower($request['query']), 'hari ini');
    });

    Http::assertSent(function (ClientRequest $request): bool {
        $prompt = $request['systemInstruction']['parts']['text'] ?? '';

        return str_contains($request->url(), 'generativelanguage.googleapis.com')
            && str_contains($prompt, 'maksimal 10 orang');
    });

    Http::assertSent(function (ClientRequest $request): bool {
        return $request->url() === 'https://api.telegram.org/bottest-bot-token/sendMessage'
            && str_contains($request['text'], 'Pegawai 1')
            && ! str_contains($request['text'], 'SSO Kepegawaian');
    });
});

it('directs Telegram personal data questions to the SSO website without calling the AI pipeline', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true]),
        '*' => Http::response(['error' => 'Unexpected request'], 500),
    ]);

    $this->postJson('/api/webhook/telegram', [
        'message' => [
            'chat' => ['id' => 12345],
            'from' => ['id' => 67890],
            'text' => 'Berapa NIP saya?',
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

it('keeps Telegram follow-up list requests in the current conversation', function () {
    Http::fake([
        'http://localhost:8000/search' => Http::response(['results' => [[
            'document_id' => 999999,
            'content' => 'Nama: Pegawai 1; tanggal pelantikan 16 Oktober 2026.',
            'hybrid_score' => 0.8,
        ]]]),
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'Daftar pegawai yang dilantik.']],
                ],
                'finishReason' => 'STOP',
            ]],
        ]),
        'https://api.telegram.org/*' => Http::response(['ok' => true]),
        '*' => Http::response(['error' => 'Unexpected request'], 500),
    ]);

    foreach ([
        'berikan 10 nama pegawai yang dilantik pada hari ini',
        'sekarang berikan 30 nama',
    ] as $text) {
        $this->postJson('/api/webhook/telegram', [
            'message' => [
                'chat' => ['id' => 12345],
                'from' => ['id' => 98765],
                'text' => $text,
            ],
        ])->assertOk()->assertJson(['ok' => true]);
    }

    $searchRequests = Http::recorded(
        fn (ClientRequest $request): bool => $request->url() === 'http://localhost:8000/search'
    )->map(fn (array $record): ClientRequest => $record[0])->values();

    expect($searchRequests)->toHaveCount(2)
        ->and($searchRequests[1]['query'])->toContain('berikan 10 nama pegawai yang dilantik')
        ->and($searchRequests[1]['query'])->toContain('sekarang berikan 30 nama');

    Http::assertSent(function (ClientRequest $request): bool {
        $prompt = $request['systemInstruction']['parts']['text'] ?? '';

        return str_contains($request->url(), 'generativelanguage.googleapis.com')
            && str_contains($prompt, 'maksimal 30 orang');
    });
});

it('splits Telegram messages before they exceed the platform length limit', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    app(TelegramService::class)->sendMessage(
        12345,
        str_repeat('a', 4200),
    );

    $sentMessages = Http::recorded()
        ->map(fn (array $record): string => (string) $record[0]['text'])
        ->values();

    expect($sentMessages)->toHaveCount(2)
        ->and($sentMessages->sum(fn (string $text): int => mb_strlen($text)))->toBe(4200)
        ->and($sentMessages->max(fn (string $text): int => mb_strlen($text)))->toBeLessThanOrEqual(4096);
});
