<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    putenv('GEMINI_API_KEY=test-key');
    putenv('GROQ_API_KEY=');
    Http::fake([
        'http://localhost:8000/search' => Http::response(['results' => []], 200),
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [[ 'text' => 'Jawaban dari AI.' ]],
                ],
                'finishReason' => 'STOP',
            ]],
        ], 200),
    ]);
});

test('chat controller includes previous messages in the AI prompt', function () {
    $user = User::factory()->create([
        'name' => 'Budi',
        'nip' => '198801012023011001',
        'role' => 'user',
    ]);

    $response = $this->actingAs($user)
        ->post(route('chat.process'), [
            'message' => 'berapa jabatan saya?',
            'history' => [
                ['role' => 'user', 'content' => 'Halo'],
                ['role' => 'assistant', 'content' => 'Halo, ada yang bisa saya bantu?'],
            ],
        ]);

    $response->assertOk();

    Http::assertSent(function ($request) {
        $text = $request['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains($text, 'RIWAYAT OBROLAN SEBELUMNYA')
            && str_contains($text, 'Halo')
            && str_contains($text, 'Halo, ada yang bisa saya bantu?');
    });
});

test('bangkom questions do not short-circuit to personal data before the bangkom context is used', function () {
    putenv('BANGKOM_API_KEY=http://bangkom.test');
    putenv('BANGKOM_API_TOKEN=token');

    $user = User::factory()->create([
        'name' => 'Budi',
        'nip' => '198801012023011001',
        'role' => 'user',
    ]);

    Http::fake([
        'http://localhost:8000/search' => Http::response(['results' => []], 200),
        'http://bangkom.test/*' => Http::response([
            'success' => true,
            'data' => [
                'nama' => 'Budi',
                'rekomendasi_bangkom' => [[
                    'aspek' => 'Komunikasi',
                    'nilai' => 4,
                    'gap' => 1,
                    'klasikal' => ['rekomendasi_pelatihan' => ['Pelatihan Komunikasi']],
                ]],
            ],
        ], 200),
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [[ 'text' => 'Berikut rekomendasi bangkom Anda.' ]],
                ],
                'finishReason' => 'STOP',
            ]],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->post(route('chat.process'), [
            'message' => 'rekomendasi bangkom saya',
            'history' => [],
        ]);

    $response->assertOk();

    Http::assertSent(function ($request) {
        $text = $request['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains($text, 'Detail Pengembangan Kompetensi (bangkom):')
            || str_contains($text, 'Rekomendasi Bangkom:');
    });
});
