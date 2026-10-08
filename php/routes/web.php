<?php

use App\Http\Controllers\ApiServiceController;
use App\Http\Controllers\Auth\KeycloakLoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FaqReviewController;
use App\Http\Controllers\PegawaiController;
use App\Services\KeycloakAuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/chat')->name('home');

/*
|--------------------------------------------------------------------------
| LOGIN SSO KEYCLOAK
|--------------------------------------------------------------------------
| Catatan: redirect_uri yang terdaftar di Keycloak adalah /login, yang
| diteruskan ke callback() oleh Fortify::loginView (FortifyServiceProvider).
| Rute /login/keycloak/callback di bawah ini hanya cadangan.
*/
Route::get('/login/keycloak', [KeycloakLoginController::class, 'redirect'])
    ->middleware(['guest', 'throttle:login'])
    ->name('keycloak.login');

Route::get('/login/keycloak/callback', [KeycloakLoginController::class, 'callback'])
    ->middleware(['guest', 'throttle:login'])
    ->name('keycloak.callback');

// ==========================================
// CHATBOT AI
// Dapat diakses tanpa login untuk pertanyaan umum berdasarkan dokumen RAG.
// Pertanyaan yang menyangkut data pribadi/NIP tetap memerlukan login.
// ==========================================
Route::get('/chat', [ChatController::class, 'index'])
    ->name('chat.index');

Route::get('/chat/c/{conversation}', [ChatController::class, 'index'])
    ->middleware('auth')
    ->name('chat.show');

Route::delete('/chat/c/{conversation}', [ChatConversationController::class, 'destroy'])
    ->middleware('auth')
    ->name('chat.conversations.destroy');

Route::delete('/chat/conversations', [ChatConversationController::class, 'destroyAll'])
    ->middleware('auth')
    ->name('chat.conversations.destroy-all');

Route::post('/chat/process', [ChatController::class, 'process'])
    ->name('chat.process')
    ->middleware('throttle:20,1');

Route::post('/chat/audits/{auditLog}/feedback', [ChatController::class, 'feedback'])
    ->name('chat.feedback')
    ->middleware('throttle:30,1');

/*
|--------------------------------------------------------------------------
| USER YANG SUDAH LOGIN
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // ==========================================
    // LOGOUT (Laravel + sesi SSO Keycloak)
    // ==========================================
    Route::post('/logout/keycloak', function (Request $request, KeycloakAuthenticationService $keycloak) {
        $url = $keycloak->logoutUrl();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $url
            ? redirect()->away($url)
            : redirect()->route('chat.index');
    })->name('logout.keycloak');

    // ==========================================
    // DATA PEGAWAI
    // ==========================================
    Route::get('/pegawai/me', [PegawaiController::class, 'me'])
        ->name('pegawai.me');

    // ==========================================
    // ADMIN ONLY
    // ==========================================
    Route::middleware('role:admin')->group(function () {

        Route::get('/dashboard', DashboardController::class)
            ->name('dashboard');
        Route::get('/dashboard/day-questions', [DashboardController::class, 'dayQuestions'])
            ->name('dashboard.day-questions');
        Route::get('/dashboard/export', [DashboardController::class, 'export'])
            ->name('dashboard.export');
        Route::delete('/dashboard/audits', [DashboardController::class, 'destroy'])
            ->name('dashboard.audits.destroy');
        Route::put('/dashboard/audits/{auditLog}/answer', [DashboardController::class, 'answer'])
            ->name('dashboard.audits.answer');
        Route::delete('/dashboard/audits/{auditLog}', [DashboardController::class, 'destroyAudit'])
            ->name('dashboard.audits.destroy-one');

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

        // FAQ Review
        Route::get('/faq-review', [FaqReviewController::class, 'index'])
            ->name('faq-review.index');

        Route::put('/faq-review/{faqEntry}', [FaqReviewController::class, 'update'])
            ->name('faq-review.update');

        Route::post('/faq-review/{faqEntry}/approve', [FaqReviewController::class, 'approve'])
            ->name('faq-review.approve');

        Route::post('/faq-review/{faqEntry}/reject', [FaqReviewController::class, 'reject'])
            ->name('faq-review.reject');

        Route::delete('/faq-review/{faqEntry}', [FaqReviewController::class, 'destroy'])
            ->name('faq-review.destroy');
    });
});

require __DIR__.'/settings.php';
