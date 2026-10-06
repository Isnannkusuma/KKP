<?php

namespace App\Services\Channels;

use App\Services\Chat\RagChatService;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected string $botToken;

    protected string $apiUrl;

    public function __construct(
        protected RagChatService $ragChatService
    ) {
        $this->botToken = config('services.telegram.bot_token');

        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;

        if (! $message) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $telegramUserId = $message['from']['id'] ?? null;
        $text = trim($message['text'] ?? '');

        if (! $chatId || ! $telegramUserId || ! $text) {
            return;
        }

        /*
         * Command /start
         */
        if ($text === '/start') {
            $this->sendMessage(
                $chatId,
                "Halo 👋\n\n"
                ."Saya adalah Asisten Layanan KKP.\n\n"
                .'Silakan kirim pertanyaan seputar informasi '
                .'kedinasan/instansi berdasarkan dokumen resmi yang tersedia.'
            );

            return;
        }

        /*
         * Command /help
         */
        if ($text === '/help') {
            $this->sendMessage(
                $chatId,
                'Silakan kirim pertanyaan seputar informasi '
                .'kedinasan, peraturan, layanan, atau dokumen resmi '
                .'yang tersedia.'
            );

            return;
        }

        /*
         * Tampilkan indikator sedang mengetik.
         */
        $this->sendChatAction($chatId, 'typing');

        if ($this->isGreeting($text)) {
            $this->sendMessage(
                $chatId,
                "Halo 👋\n\n"
                ."Saya Asisten Layanan KKP.\n"
                .'Saya dapat membantu menjawab pertanyaan seputar '
                .'peraturan, layanan, dan informasi kedinasan berdasarkan '
                ."dokumen resmi yang tersedia.\n\n"
                .'Silakan kirim pertanyaan Anda.'
            );

            return;
        }

        $answer = $this->ragChatService->process($text);

        $this->sendMessage(
            $chatId,
            $answer
        );
    }

    public function sendMessage(
        string|int $chatId,
        string $text
    ): void {
        Http::timeout(30)
            ->post("{$this->apiUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
            ])
            ->throw();
    }

    protected function isGreeting(string $text): bool
    {
        $text = mb_strtolower(trim($text));

        $greetings = [
            'halo',
            'hai',
            'hi',
            'hello',
            'helo',
            'p',
            'permisi',
            'selamat pagi',
            'selamat siang',
            'selamat sore',
            'selamat malam',
        ];

        return in_array($text, $greetings, true);
    }

    protected function sendChatAction(
        string|int $chatId,
        string $action
    ): void {
        Http::timeout(10)
            ->post("{$this->apiUrl}/sendChatAction", [
                'chat_id' => $chatId,
                'action' => $action,
            ]);
    }
}
