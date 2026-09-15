<?php

use App\Http\Controllers\ApiServiceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PegawaiController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/chat')->name('home');

/*
|--------------------------------------------------------------------------
| USER YANG SUDAH LOGIN
|--------------------------------------------------------------------------
*/

// ==========================================
// CHATBOT AI
// Dapat diakses tanpa login untuk pertanyaan umum berdasarkan dokumen RAG.
// Pertanyaan yang menyangkut data pribadi/NIP tetap memerlukan login.
// ==========================================
Route::get('/chat', [ChatController::class, 'index'])
    ->name('chat.index');

Route::post('/chat/process', [ChatController::class, 'process'])
    ->name('chat.process')
    ->middleware('throttle:20,1');

Route::middleware('auth')->group(function () {

    // ==========================================
    // DATA PEGAWAI
    // ==========================================

    Route::get('/pegawai/me', [PegawaiController::class, 'me'])
        ->name('pegawai.me');

    // ==========================================
    // ADMIN ONLY
    // ==========================================

    Route::middleware('role:admin')->group(function () {

        Route::inertia('/dashboard', 'dashboard')
            ->name('dashboard');

        // Dokumen / RAG
        Route::get('/dokumen', [DocumentController::class, 'index'])
            ->name('dokumen.index');

        Route::post('/dokumen', [DocumentController::class, 'store'])
            ->name('dokumen.store');

        Route::get('/dokumen/{document}/download', [DocumentController::class, 'download'])
            ->name('dokumen.download');

        Route::post('/dokumen/{document}/retry', [DocumentController::class, 'retry'])
            ->name('dokumen.retry');

        Route::get('/dokumen/{document}', [DocumentController::class, 'show'])
            ->name('dokumen.show');

        Route::put('/dokumen/{document}', [DocumentController::class, 'update'])
            ->name('dokumen.update');

        Route::delete('/dokumen/{document}', [DocumentController::class, 'destroy'])
            ->name('dokumen.destroy');

        // Kategori
        Route::get('/kategori', [CategoryController::class, 'index'])
            ->name('kategori.index');

        Route::post('/kategori', [CategoryController::class, 'store'])
            ->name('kategori.store');

        Route::put('/kategori/{category}', [CategoryController::class, 'update'])
            ->name('kategori.update');

        Route::delete('/kategori/{category}', [CategoryController::class, 'destroy'])
            ->name('kategori.destroy');

        // Service API yang dapat dikonfigurasi dari dashboard
        Route::get('/service-api', [ApiServiceController::class, 'index'])
            ->name('api-services.index');

        Route::post('/service-api', [ApiServiceController::class, 'store'])
            ->name('api-services.store');

        Route::post('/service-api/inspect', [ApiServiceController::class, 'inspect'])
            ->name('api-services.inspect');

        Route::put('/service-api/{apiService}', [ApiServiceController::class, 'update'])
            ->name('api-services.update');

        Route::delete('/service-api/{apiService}', [ApiServiceController::class, 'destroy'])
            ->name('api-services.destroy');

        Route::post('/service-api/{apiService}/test', [ApiServiceController::class, 'test'])
            ->name('api-services.test');
    });
});

require __DIR__.'/settings.php';
