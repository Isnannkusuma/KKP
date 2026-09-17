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
                    'parts' => [['text' => 'Jawaban dari AI.']],
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

test('chat controller falls back to groq when gemini key is empty', function () {
    putenv('GEMINI_API_KEY=');
    putenv('GROQ_API_KEY=test-groq-key');

    $user = User::factory()->create([
        'name' => 'Budi',
        'nip' => '198801012023011001',
        'role' => 'user',
    ]);

    Http::fake([
        'http://localhost:8000/search' => Http::response(['results' => []], 200),
        'https://api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => 'Jawaban dari Groq.',
                ],
                'finish_reason' => 'stop',
            ]],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->post(route('chat.process'), [
            'message' => 'Halo, apa kabar?',
            'history' => [],
        ]);

    $response->assertOk();
    expect($response->json('answer'))->toContain('Jawaban dari Groq');
});

test('guest users can ask general rag questions without authenticating', function () {
    Http::fake([
        'http://localhost:8000/search' => Http::response([
            'results' => [[
                'document_id' => 1,
                'content' => 'Dokumen SOP: pegawai wajib hadir tepat waktu.',
                'hybrid_score' => 0.8,
            ]],
        ], 200),
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'Jawaban umum dari RAG.']],
                ],
                'finishReason' => 'STOP',
            ]],
        ], 200),
    ]);

    $response = $this->post(route('chat.process'), [
        'message' => 'apa aturan kehadiran pegawai?',
        'history' => [],
    ]);

    $response->assertOk();
    expect($response->json('require_login'))->toBeFalse();
    expect($response->json('answer'))->toContain('Jawaban umum dari RAG');
});

test('logged in users can ask questions about RAG documents', function () {
    $user = User::factory()->create([
        'name' => 'Budi',
        'nip' => '198801012023011001',
        'role' => 'user',
    ]);

    Http::fake([
        'http://localhost:8000/search' => Http::response([
            'results' => [[
                'document_id' => 1,
                'content' => 'Dokumen RAG menjelaskan prosedur pengembangan kompetensi.',
                'hybrid_score' => 0.8,
            ]],
        ], 200),
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'Jawaban dari dokumen RAG.']],
                ],
                'finishReason' => 'STOP',
            ]],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->post(route('chat.process'), [
            'message' => 'berdasarkan file RAG, jelaskan pengembangan kompetensi',
            'history' => [],
        ]);

    $response->assertOk();
    expect($response->json('answer'))->toContain('Jawaban dari dokumen RAG');

    Http::assertSent(function ($request) {
        $text = $request['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains($text, 'Dokumen RAG menjelaskan prosedur pengembangan kompetensi.');
    });
});

test('logged in personal data questions use RAG context before personal context', function () {
    $user = User::factory()->create([
        'name' => 'Budi',
        'nip' => '198801012023011001',
        'role' => 'user',
    ]);

    Http::fake([
        'http://localhost:8000/search' => Http::response([
            'results' => [[
                'document_id' => 1,
                'content' => 'Dokumen RAG menjelaskan ketentuan jabatan pegawai.',
                'hybrid_score' => 0.8,
            ]],
        ], 200),
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'Jawaban yang diperkuat dokumen RAG.']],
                ],
                'finishReason' => 'STOP',
            ]],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->post(route('chat.process'), [
            'message' => 'apa jabatan saya?',
            'history' => [],
        ]);

    $response->assertOk();
    expect($response->json('answer'))->toContain('Jawaban yang diperkuat dokumen RAG');

    Http::assertSent(function ($request) {
        $text = $request['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains($text, 'Dokumen RAG menjelaskan ketentuan jabatan pegawai.')
            && str_contains($text, 'CATATAN SISTEM: Data pegawai')
            && strpos($text, 'Dokumen RAG menjelaskan ketentuan jabatan pegawai.') < strpos($text, 'CATATAN SISTEM: Data pegawai');
    });
});

test('guest users are redirected to login for personal data questions', function () {
    $response = $this->post(route('chat.process'), [
        'message' => 'berapa nip saya?',
        'history' => [],
    ]);

    $response->assertOk();
    expect($response->json('require_login'))->toBeTrue();
    expect($response->json('answer'))->toContain('login');
});

test('guest users are redirected to login for complete personal summary requests', function () {
    $response = $this->post(route('chat.process'), [
        'message' => 'informasi pribadi saya secara lengkap',
        'history' => [],
    ]);

    $response->assertOk();
    expect($response->json('require_login'))->toBeTrue();
    expect($response->json('answer'))->toContain('login');
});

test('logged in users get a structured personal summary for complete personal detail requests', function () {
    $user = User::factory()->create([
        'name' => 'Budi',
        'nip' => '198801012023011001',
        'role' => 'user',
    ]);

    $response = $this->actingAs($user)
        ->post(route('chat.process'), [
            'message' => 'informasi pribadi saya secara lengkap',
            'history' => [],
        ]);

    $response->assertOk();
    expect($response->json('answer'))->toContain('Berikut informasi data kepegawaian Anda yang tersedia');
    expect($response->json('answer'))->toContain('Nama:');
    expect($response->json('answer'))->toContain('NIP:');
});

test('talent mapping questions resolve from the pemetaan service before the generic AI response', function () {
    putenv('PEMETAAN_API_KEY=http://pemetaan.test');

    $user = User::factory()->create([
        'name' => 'Budi',
        'nip' => '198801012023011001',
        'role' => 'user',
    ]);

    Http::fake([
        'http://localhost:8000/search' => Http::response(['results' => []], 200),
        'http://pemetaan.test/*' => Http::response([
            'success' => true,
            'data' => [
                'nip' => $user->nip,
                'n_talent' => '85.50',
                'box' => '2',
                'smb_x' => '120',
                'smb_y' => '90',
            ],
        ], 200),
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'Jawaban dari AI.']],
                ],
                'finishReason' => 'STOP',
            ]],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->post(route('chat.process'), [
            'message' => 'pemetaan talenta saya',
            'history' => [],
        ]);

    $response->assertOk();
    expect($response->json('answer'))->toContain('HASIL PEMETAAN KINERJA DAN TALENTA');
    expect($response->json('answer'))->toContain('Nilai Talent');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'http://pemetaan.test/')
            && str_contains($request->url(), rawurlencode($user->nip));
    });
});

test('bangkom questions return personal recommendations without calling Gemini when RAG has results', function () {
    putenv('BANGKOM_API_KEY=http://bangkom.test');
    putenv('BANGKOM_API_TOKEN=token');

    $user = User::factory()->create([
        'name' => 'Budi',
        'nip' => '198801012023011001',
        'role' => 'user',
    ]);

    Http::fake([
        'http://localhost:8000/search' => Http::response(['results' => [[
            'hybrid_score' => 0.9,
            'content' => 'Konteks dokumen umum.',
            'document_id' => 999999,
        ]]], 200),
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
    ]);

    $response = $this->actingAs($user)
        ->post(route('chat.process'), [
            'message' => 'rekomendasi bangkom saya',
            'history' => [],
        ]);

    $response->assertOk();
    expect($response->json('answer'))->toContain('Rekomendasi Bangkom 1');
    expect($response->json('answer'))->toContain('Pelatihan Komunikasi');

    Http::assertNotSent(function ($request) {
        return str_starts_with($request->url(), 'https://generativelanguage.googleapis.com/');
    });
});

test('bangkom questions return a clear unavailable message instead of the out-of-scope response when the API has no data', function () {
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
            'data' => null,
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->post(route('chat.process'), [
            'message' => 'berikan saya rekomendasi bangkom saya',
            'history' => [],
        ]);

    $response->assertOk();
    expect($response->json('answer'))->toContain('Data rekomendasi bangkom Anda belum tersedia');
});
