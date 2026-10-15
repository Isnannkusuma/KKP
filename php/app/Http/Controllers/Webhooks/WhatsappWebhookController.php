<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Channels\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        protected WhatsappService $whatsappService
    ) {}

    public function handle(Request $request): Response|JsonResponse
    {
        if ($request->isMethod('get')) {
            return $this->verify($request);
        }

        if (! $this->hasValidSignature($request)) {
            return response()->json(['ok' => false], 403);
        }

        try {
            $this->whatsappService->handleUpdate($request->all());

            return response()->json(['ok' => true]);
        } catch (\Throwable $exception) {
            Log::error('WhatsApp webhook processing failed', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['ok' => false], 500);
        }
    }

    protected function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');
        $verifyToken = config('services.whatsapp.verify_token');

        if (
            $mode === 'subscribe'
            && is_string($verifyToken)
            && $verifyToken !== ''
            && is_string($token)
            && hash_equals($verifyToken, $token)
            && is_string($challenge)
        ) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    protected function hasValidSignature(Request $request): bool
    {
        $appSecret = config('services.whatsapp.app_secret');
        $signature = $request->header('X-Hub-Signature-256');

        if (! is_string($appSecret) || $appSecret === '' || ! is_string($signature)) {
            return false;
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
