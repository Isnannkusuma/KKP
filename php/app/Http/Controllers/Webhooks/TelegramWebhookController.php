<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Channels\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        Log::info('Telegram webhook received', [
            'update_id' => $request->input('update_id'),
            'chat_id' => $request->input('message.chat.id'),
            'telegram_user_id' => $request->input('message.from.id'),
            'text' => $request->input('message.text'),
        ]);

        try {
            $this->telegramService->handleUpdate(
                $request->all()
            );

            return response()->json([
                'ok' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram webhook error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Tetap 200 agar Telegram tidak terus-menerus
            // mengirim ulang update yang sama.
            return response()->json([
                'ok' => false,
            ], 200);
        }
    }
}
