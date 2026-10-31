<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ChatAudioController extends Controller
{
    public function transcribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:20480', 'mimes:mp3,mp4,m4a,wav,webm,ogg,aac'],
        ]);

        $apiKey = config('ai.providers.openai.key');

        if (! $apiKey) {
            return response()->json([
                'message' => 'Layanan audio OpenAI belum dikonfigurasi.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $audio = $validated['audio'];
        $transcriptionModel = config('ai.providers.openai.transcription_model', 'gpt-transcribe');
        $transcriptionOptions = [
            'model' => $transcriptionModel,
            'prompt' => 'Bahasa Indonesia. Transkripsikan ucapan dengan ejaan Bahasa Indonesia. Pertahankan kata sebagaimana diucapkan dan jangan menerjemahkannya.',
        ];

        if ($transcriptionModel === 'gpt-transcribe') {
            $transcriptionOptions['languages'] = ['id'];
        } else {
            $transcriptionOptions['language'] = 'id';
        }

        try {
            $response = Http::withToken($apiKey)
                ->connectTimeout(5)
                ->timeout(90)
                ->attach('file', $audio->getContent(), $audio->getClientOriginalName())
                ->post($this->audioEndpoint('transcriptions'), $transcriptionOptions);
        } catch (ConnectionException $exception) {
            return $this->openAiConnectionError($exception, 'transcription');
        }

        if (! $response->successful()) {
            return $this->openAiError($response, 'transcription');
        }

        $text = trim((string) $response->json('text', ''));

        if ($text === '') {
            return response()->json([
                'message' => 'Ucapan tidak terdeteksi. Silakan coba berbicara lebih jelas.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['text' => $text]);
    }

    public function speech(Request $request): Response
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:4096'],
        ]);

        $apiKey = config('ai.providers.openai.key');

        if (! $apiKey) {
            return response()->json([
                'message' => 'Layanan audio OpenAI belum dikonfigurasi.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $response = Http::withToken($apiKey)
                ->connectTimeout(5)
                ->timeout(90)
                ->post($this->audioEndpoint('speech'), [
                    'model' => config('ai.providers.openai.speech_model', 'gpt-4o-mini-tts'),
                    'voice' => config('ai.providers.openai.speech_voice', 'alloy'),
                    'input' => $validated['text'],
                    'instructions' => 'Ucapkan seluruh teks ini dalam Bahasa Indonesia baku dengan pelafalan penutur asli Indonesia. Jangan menerjemahkan, mengganti, menambah, atau menghilangkan kata. Baca dengan jelas, alami, dan bertempo sedang.',
                    'response_format' => 'mp3',
                ]);
        } catch (ConnectionException $exception) {
            return $this->openAiConnectionError($exception, 'speech');
        }

        if (! $response->successful()) {
            return $this->openAiError($response, 'speech');
        }

        return response($response->body())
            ->header('Content-Type', 'audio/mpeg')
            ->header('Cache-Control', 'no-store');
    }

    private function audioEndpoint(string $operation): string
    {
        $baseUrl = rtrim(config('ai.providers.openai.url', 'https://api.openai.com/v1'), '/');

        return $baseUrl.'/audio/'.$operation;
    }

    private function openAiError(HttpResponse $response, string $operation): JsonResponse
    {
        Log::warning('OpenAI audio request failed.', [
            'operation' => $operation,
            'status' => $response->status(),
        ]);

        return response()->json([
            'message' => 'OpenAI tidak dapat memproses audio saat ini. Silakan coba lagi.',
        ], Response::HTTP_BAD_GATEWAY);
    }

    private function openAiConnectionError(ConnectionException $exception, string $operation): JsonResponse
    {
        Log::warning('OpenAI audio connection failed.', [
            'operation' => $operation,
            'error' => $exception->getMessage(),
        ]);

        return response()->json([
            'message' => 'Tidak dapat terhubung ke layanan audio OpenAI. Silakan coba lagi.',
        ], Response::HTTP_BAD_GATEWAY);
    }
}
