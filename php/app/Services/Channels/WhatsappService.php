<?php

namespace App\Services\Channels;

use App\Services\Chat\RagChatService;
use Illuminate\Support\Facades\Http;

class WhatsappService
{
    public function __construct(
        protected RagChatService $ragChatService
    ) {}

    public function handleUpdate(array $update): void
    {
        foreach ($update['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['messages'] ?? [] as $message) {
                    $senderId = $message['from'] ?? null;
                    $text = trim($message['text']['body'] ?? '');

                    if (($message['type'] ?? null) !== 'text' || ! $senderId || $text === '') {
                        continue;
                    }

                    $this->sendMessage(
                        $senderId,
                        $this->ragChatService->process($text)
                    );
                }
            }
        }
    }

    public function sendMessage(string $recipient, string $text): void
    {
        $apiVersion = config('services.whatsapp.api_version');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        Http::withToken(config('services.whatsapp.access_token'))
            ->timeout(30)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'body' => mb_substr($text, 0, 4096),
                ],
            ])
            ->throw();
    }
}
