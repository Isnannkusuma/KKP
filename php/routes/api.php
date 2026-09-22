<?php

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
});