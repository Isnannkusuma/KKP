<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'ai.providers.openai.key' => 'test-openai-key',
        'ai.providers.openai.url' => 'https://api.openai.com/v1',
        'ai.providers.openai.speech_model' => 'gpt-4o-mini-tts',
        'ai.providers.openai.speech_voice' => 'marin',
        'ai.providers.openai.transcription_model' => 'gpt-transcribe',
    ]);
});

test('voice input is transcribed by OpenAI and returns Indonesian text', function () {
    Http::fake([
        'https://api.openai.com/v1/audio/transcriptions' => Http::response([
            'text' => 'Bagaimana prosedur cuti?',
        ]),
    ]);

    $response = $this->postJson(route('chat.audio.transcribe'), [
        'audio' => UploadedFile::fake()->createWithContent(
            'recording.wav',
            'RIFF'.pack('V', 36).'WAVEfmt '.pack('V', 16).pack('v', 1).pack('v', 1)
                .pack('V', 8000).pack('V', 16000).pack('v', 2).pack('v', 16).'data'.pack('V', 0),
        ),
    ]);

    $response->assertOk()->assertJsonPath('text', 'Bagaimana prosedur cuti?');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.openai.com/v1/audio/transcriptions'
            && $request->hasHeader('Authorization', 'Bearer test-openai-key')
            && str_contains($request->body(), 'gpt-transcribe')
            && str_contains($request->body(), 'Bahasa Indonesia')
            && str_contains($request->body(), 'languages');
    });
});

test('assistant audio is generated with OpenAI TTS and returned as mp3', function () {
    Http::fake([
        'https://api.openai.com/v1/audio/speech' => Http::response('fake-mp3-audio', 200, [
            'Content-Type' => 'audio/mpeg',
        ]),
    ]);

    $response = $this->postJson(route('chat.audio.speech'), [
        'text' => 'Jawaban dalam bahasa Indonesia.',
    ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg')
        ->assertSee('fake-mp3-audio');

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->url() === 'https://api.openai.com/v1/audio/speech'
            && $request->hasHeader('Authorization', 'Bearer test-openai-key')
            && ($data['model'] ?? null) === 'gpt-4o-mini-tts'
            && ($data['voice'] ?? null) === 'marin'
            && ($data['input'] ?? null) === 'Jawaban dalam bahasa Indonesia.'
            && str_contains($data['instructions'] ?? '', 'penutur asli Indonesia')
            && ($data['response_format'] ?? null) === 'mp3';
    });
});

test('audio endpoints report missing OpenAI configuration', function () {
    config(['ai.providers.openai.key' => null]);

    $this->postJson(route('chat.audio.speech'), [
        'text' => 'Test',
    ])->assertStatus(503)->assertJsonPath('message', 'Layanan audio OpenAI belum dikonfigurasi.');

    Http::assertNothingSent();
});

test('widget audio endpoint requires a valid widget origin', function () {
    $this->postJson('/api/widget/audio/speech', [
        'text' => 'Test',
    ])->assertForbidden();
});
