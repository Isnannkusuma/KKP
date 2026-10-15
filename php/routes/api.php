<?php

use App\Http\Controllers\Webhooks\TelegramWebhookController;
use App\Http\Controllers\Webhooks\WhatsappWebhookController;
use App\Http\Controllers\WidgetAuthController;
use App\Http\Controllers\WidgetChatController;
use App\Http\Middleware\VerifyWidgetOrigin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Widget API Routes
|--------------------------------------------------------------------------
| Endpoint publik (tanpa sesi Laravel) yang dipanggil oleh widget chat
| dari domain lain yang sudah di-whitelist. Middleware VerifyWidgetOrigin
| menangani validasi widget_key + Origin + header CORS.
*/

Route::middleware(['throttle:30,1', VerifyWidgetOrigin::class])->group(function () {
    Route::match(['post', 'options'], '/widget/chat', [WidgetChatController::class, 'process']);
    Route::match(['post', 'options'], '/widget/chat/stream', [WidgetChatController::class, 'stream']);
    Route::match(['post', 'options'], '/widget/auth/logout', [WidgetAuthController::class, 'logout']);
});

// Login dibatasi lebih ketat (5x/menit) untuk cegah brute-force NIP+password
Route::middleware(['throttle:5,1', VerifyWidgetOrigin::class])->group(function () {
    Route::match(['post', 'options'], '/widget/auth/login', [WidgetAuthController::class, 'login']);
});

Route::post(
    '/webhook/telegram',
    [TelegramWebhookController::class, 'handle']
);

Route::match(
    ['get', 'post'],
    '/webhook/whatsapp',
    [WhatsappWebhookController::class, 'handle']
);
