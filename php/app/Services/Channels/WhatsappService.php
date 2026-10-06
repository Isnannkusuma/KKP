<?php

namespace App\Services\Channels;

use App\Services\Chat\RagChatService;

class WhatsappService
{
    protected $accsessToken;

    protected $phoneNumberId;

    protected $ragChatService;

    public function __construct(RagChatService $ragChatService)
    {
        $this->accsessToken = env('WHATSAPP_ACCESS_TOKEN');
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $this->ragChatService = $ragChatService;
    }

    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;
        if ($message) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $whatsappUserId = $message['from']['id'] ?? null;
        $text = trim($message['text'] ?? '');

        if (! $chatId || ! $whatsappUserId || ! $text) {
            return;
        }

    }
}
