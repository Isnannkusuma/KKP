<?php

use App\Http\Controllers\ChatController;
use App\Services\ApiServiceRunner;
use App\Services\BangkomPegawaiService;
use App\Services\PemetaanPegawaiService;
use App\Services\PortalPegawaiService;

function makeChatController(): ChatController
{
    return new ChatController(
        new PortalPegawaiService(),
        new PemetaanPegawaiService(),
        new BangkomPegawaiService(),
        new ApiServiceRunner(),
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
        . "Nama: Budi\n"
        . "NIP: 199901012024011001\n"
        . "Jabatan: Penata Muda\n"
        . "Pangkat: Penata Muda\n"
        . "Unit Kerja: Kantor Pusat\n";

    $toAnswer = function (string $message, string $context): string {
        $method = new ReflectionMethod(ChatController::class, 'personalContextToAnswer');
        $method->setAccessible(true);

        return $method->invoke($controller, $context, $message);
    };

    expect($toAnswer('apa pangkat saya?', $personalContext))->toContain('Pangkat:')
        ->and($toAnswer('apa unit kerja saya?', $personalContext))->toContain('Unit Kerja:');
});
