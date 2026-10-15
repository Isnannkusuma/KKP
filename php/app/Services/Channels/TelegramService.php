<?php

namespace App\Services\Channels;

use App\Http\Controllers\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected const EMPLOYMENT_KEYWORDS = [
        'kepegawaian',
        'pegawai',
        'asn',
        'pns',
        'cpns',
        'pppk',
        'nip',
        'jabatan',
        'pangkat',
        'golongan',
        'kenaikan gaji',
        'kenaikan pangkat',
        'gaji berkala',
        'cuti',
        'pensiun',
        'bup',
        'talenta',
        'pemetaan',
        'bangkom',
        'pengembangan kompetensi',
        'disiplin',
    ];

    protected const EMPLOYMENT_LOGIN_URL = 'https://csai.sdmao-kkp.online/';

    protected string $botToken;

    protected string $apiUrl;

    public function __construct(
        protected ChatController $chatController
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

        if ($this->isEmploymentQuestion($text)) {
            $this->sendMessage(
                $chatId,
                'Untuk pertanyaan atau layanan terkait kepegawaian, silakan buka '
                .self::EMPLOYMENT_LOGIN_URL
                .' lalu masuk menggunakan SSO Kepegawaian. Setelah login, Anda dapat '
                .'mengakses bantuan yang sesuai dengan data kepegawaian Anda.'
            );

            return;
        }

        /*
         * Keep unanswered-question tracking isolated per Telegram user.
         */
        $chatRequest = Request::create('/api/chat', 'POST', [
            'message' => $text,
        ]);
        $chatRequest->attributes->set(
            'chat_user_identifier',
            'telegram:'.hash('sha256', (string) $telegramUserId)
        );

        $this->sendChatAction($chatId, 'typing');

        $response = $this->chatController->process($chatRequest);
        $answer = $response->getData(true)['answer'] ?? null;

        if (! is_string($answer) || trim($answer) === '') {
            throw new \UnexpectedValueException(
                'ChatController returned an empty Telegram answer.'
            );
        }

        $this->sendMessage($chatId, $answer);
    }

    protected function isEmploymentQuestion(string $text): bool
    {
        $normalized = mb_strtolower($text);

        foreach (self::EMPLOYMENT_KEYWORDS as $keyword) {
            if (preg_match(
                '/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}])/u',
                $normalized
            ) === 1) {
                return true;
            }
        }

        return false;
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
