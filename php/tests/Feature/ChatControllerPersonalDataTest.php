<?php

use App\Http\Controllers\ChatController;
use App\Services\ApiServiceRunner;
use App\Services\BangkomPegawaiService;
use App\Services\PemetaanPegawaiService;
use App\Services\PortalPegawaiService;

function makeChatController(): ChatController
{
    return new ChatController(
        new PortalPegawaiService,
        new PemetaanPegawaiService,
        new BangkomPegawaiService,
        new ApiServiceRunner,
    );
}

it('treats unit kerja and pangkat questions as personal data questions', function () {
    $controller = makeChatController();

    $shouldReturnDirectly = function (string $message): bool {
        $method = new ReflectionMethod(ChatController::class, 'shouldReturnPersonalDataDirectly');
        $method->setAccessible(true);

        return $method->invoke($controller, $message);
    };

    expect($shouldReturnDirectly('apa unit kerja saya?'))->toBeTrue()
        ->and($shouldReturnDirectly('apa pangkat saya?'))->toBeTrue();
});

it('extracts pangkat and unit kerja from personal context', function () {
    $controller = makeChatController();

    $personalContext = "DATA PRIBADI PENGGUNA:\n"
        ."Nama: Budi\n"
        ."NIP: 199901012024011001\n"
        ."Jabatan: Penata Muda\n"
        ."Pangkat: Penata Muda\n"
        ."Unit Kerja: Kantor Pusat\n";

    $toAnswer = function (string $message, string $context): string {
        $method = new ReflectionMethod(ChatController::class, 'personalContextToAnswer');
        $method->setAccessible(true);

        return $method->invoke($controller, $context, $message);
    };

    expect($toAnswer('apa pangkat saya?', $personalContext))->toContain('Pangkat:')
        ->and($toAnswer('apa unit kerja saya?', $personalContext))->toContain('Unit Kerja:');
});

it('uses gender-aware salutation in greeting based on user data', function () {
    $controller = makeChatController();

    $withGreeting = function (string $answer, object $user): string {
        $method = new ReflectionMethod(ChatController::class, 'withGreeting');
        $method->setAccessible(true);

        return $method->invoke($controller, $answer, $user);
    };

    $maleUser = (object) ['name' => 'Budi Santoso', 'nip' => '199901012024011001', 'jk' => 'L'];
    $femaleUser = (object) ['name' => 'Sari Wulandari', 'nip' => '199901012024011002', 'jk' => 'P'];

    expect($withGreeting('Data BUP Anda.', $maleUser))->toContain('Bapak Budi Santoso')
        ->and($withGreeting('Data BUP Anda.', $femaleUser))->toContain('Ibu Sari Wulandari');
});

it('returns document context when an AI provider is unavailable', function () {
    $controller = makeChatController();
    $method = new ReflectionMethod(ChatController::class, 'documentContextFallback');
    $method->setAccessible(true);

    $answer = $method->invoke($controller, [
        "SUMBER 1:\nInformasi pengajuan cuti untuk PPPK.",
    ]);

    expect($answer)->toContain('Layanan AI sedang tidak tersedia.')
        ->and($answer)->toContain('Informasi pengajuan cuti untuk PPPK.')
        ->and($answer)->not->toContain('SUMBER 1:');
});
