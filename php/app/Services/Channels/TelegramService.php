<?php

namespace App\Services\Channels;

use App\Http\Controllers\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected const MESSAGE_LIMIT = 4000;

    protected string $botToken;

    protected string $apiUrl;

    public function __construct(
        protected ChatController $chatController
    ) {
        $this->botToken = config('services.telegram.bot_token');

        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * @param  array<string, mixed>  $update
     */
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

        $historyCacheKey = 'telegram:chat-history:'.hash('sha256', (string) $telegramUserId);

        /*
         * Command /start
         */
        if ($text === '/start') {
            Cache::forget($historyCacheKey);
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

        $history = Cache::get($historyCacheKey, []);
        if (! is_array($history)) {
            $history = [];
        }

        /*
         * Keep unanswered-question tracking isolated per Telegram user.
         */
        $chatRequest = Request::create('/api/chat', 'POST', [
            'message' => $text,
            'history' => $history,
        ]);
        $chatRequest->attributes->set(
            'chat_user_identifier',
            'telegram:'.hash('sha256', (string) $telegramUserId)
        );

        $this->sendChatAction($chatId, 'typing');

        $response = $this->chatController->process($chatRequest);
        $responseData = $response->getData(true);
        $answer = $responseData['answer'] ?? null;

        if (! is_string($answer) || trim($answer) === '') {
            throw new \UnexpectedValueException(
                'ChatController returned an empty Telegram answer.'
            );
        }

        if (! empty($responseData['require_login'])) {
            $answer = 'Untuk pertanyaan terkait data pribadi atau NIP, silakan buka '
                .'https://csai.sdmao-kkp.online/ lalu masuk menggunakan SSO Kepegawaian.';
        } else {
            $history[] = ['role' => 'user', 'content' => $text];
            $history[] = ['role' => 'assistant', 'content' => $answer];
            Cache::put(
                $historyCacheKey,
                array_slice($history, -12),
                now()->addHours(2),
            );
        }

        $this->sendMessage($chatId, $answer);
    }

    public function sendMessage(
        string|int $chatId,
        string $text
    ): void {
        $parts = $this->splitMessage($text);
        if ($parts === []) {
            throw new \InvalidArgumentException('Telegram message cannot be empty.');
        }

        foreach ($parts as $part) {
            Http::timeout(30)
                ->post("{$this->apiUrl}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $part,
                ])
                ->throw();
        }
    }

    /**
     * @return list<string>
     */
    protected function splitMessage(string $text): array
    {
        $parts = [];

        while (mb_strlen($text) > self::MESSAGE_LIMIT) {
            $part = mb_substr($text, 0, self::MESSAGE_LIMIT);
            $splitAt = mb_strrpos($part, "\n");

            if ($splitAt === false || $splitAt < self::MESSAGE_LIMIT / 2) {
                $splitAt = mb_strrpos($part, ' ');
            }

            if ($splitAt === false || $splitAt < self::MESSAGE_LIMIT / 2) {
                $splitAt = self::MESSAGE_LIMIT;
            }

            $parts[] = trim(mb_substr($text, 0, $splitAt));
            $text = ltrim(mb_substr($text, $splitAt));
        }

        if (trim($text) !== '') {
            $parts[] = trim($text);
        }

        return $parts;
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
