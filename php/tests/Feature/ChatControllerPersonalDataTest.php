<?php

use App\Http\Controllers\ChatController;
use App\Services\ApiServiceRunner;
use App\Services\BangkomPegawaiService;
use App\Services\PemetaanPegawaiService;
use App\Services\PortalPegawaiService;
use App\Services\ProposalPdfLookupService;

function makeChatController(): ChatController
{
    return new ChatController(
        new PortalPegawaiService,
        new PemetaanPegawaiService,
        new BangkomPegawaiService,
        new ApiServiceRunner,
        new ProposalPdfLookupService,
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

it('does not require login for general employee questions but protects personal information', function () {
    $controller = makeChatController();
    $requiresLogin = function (string $message) use ($controller): bool {
        $method = new ReflectionMethod(ChatController::class, 'requiresLoginForPersonalData');
        $method->setAccessible(true);

        return $method->invoke($controller, $message);
    };

    expect($requiresLogin('apa kebijakan cuti pegawai?'))->toBeFalse()
        ->and($requiresLogin('siapa saja pegawai yang dilantik hari ini?'))->toBeFalse()
        ->and($requiresLogin('berapa NIP pegawai saya?'))->toBeTrue()
        ->and($requiresLogin('apa saja yang perlu saya kembangkan dari potensi saya?'))->toBeTrue()
        ->and($requiresLogin('apa potensi pegawai yang perlu dikembangkan?'))->toBeFalse()
        ->and($requiresLogin('informasi pribadi saya secara lengkap'))->toBeTrue();
});

it('limits AiRHA requests to authenticated personal employment topics', function () {
    $controller = makeChatController();
    $method = new ReflectionMethod(ChatController::class, 'airhaTopicsForPersonalQuestion');
    $user = (object) ['nip' => '198801012023011001'];

    expect($method->invoke($controller, 'Bagaimana kompetensi saya?', $user))->toBe(['kompetensi'])
        ->and($method->invoke($controller, 'Apa potensi dan kompetensi saya?', $user))->toBe(['potensi', 'kompetensi'])
        ->and($method->invoke($controller, 'Apa saja yang perlu saya kembangkan dari potensi saya?', $user))->toBe(['potensi', 'kompetensi'])
        ->and($method->invoke($controller, 'Bagaimana kompetensi pegawai?', $user))->toBe([])
        ->and($method->invoke($controller, 'Bagaimana kompetensi saya?', null))->toBe([])
        ->and($method->invoke($controller, 'Apa kompetensi saya untuk NIP 198801012023011002?', $user))->toBe([]);
});

it('does not treat polite requests for regulations as personal profile questions', function () {
    $controller = makeChatController();
    $isPersonalSummary = new ReflectionMethod(ChatController::class, 'isPersonalSummaryQuestion');
    $isPersonalContext = new ReflectionMethod(ChatController::class, 'isPersonalDataContextQuery');
    $shouldReturnPersonalData = new ReflectionMethod(ChatController::class, 'shouldReturnPersonalDataDirectly');
    $requiresLogin = new ReflectionMethod(ChatController::class, 'requiresLoginForPersonalData');
    $buildPersonalContext = new ReflectionMethod(ChatController::class, 'buildPersonalContext');
    $question = 'Tolong berikan saya informasi terkait Permen KP nomor 3 tahun 2026.';
    $policyQuestion = 'Saya ingin tahu Permenpan RB tentang kebijakan pangkat dan pensiun.';

    expect($isPersonalSummary->invoke($controller, $question))->toBeFalse()
        ->and($isPersonalContext->invoke($controller, $question))->toBeFalse()
        ->and($buildPersonalContext->invoke($controller, $question, (object) ['nip' => null]))->toBe('')
        ->and($shouldReturnPersonalData->invoke($controller, $policyQuestion))->toBeFalse()
        ->and($requiresLogin->invoke($controller, $policyQuestion))->toBeFalse();
});

it('recognizes personal status and leave clarification without treating regulations as profile requests', function () {
    $controller = makeChatController();
    $isEmploymentStatus = new ReflectionMethod(ChatController::class, 'isEmploymentStatusQuestion');
    $isLeaveClarification = new ReflectionMethod(ChatController::class, 'isLeaveClarificationRequest');
    $isLeaveQuestion = new ReflectionMethod(ChatController::class, 'isLeaveQuestion');
    $employmentStatusFromData = new ReflectionMethod(ChatController::class, 'employmentStatusFromData');

    expect($isEmploymentStatus->invoke($controller, 'Apakah status kepegawaian saya PNS atau PPPK?'))->toBeTrue()
        ->and($isLeaveClarification->invoke($controller, 'Saya ingin mengajukan izin cuti'))->toBeTrue()
        ->and($isLeaveClarification->invoke($controller, 'Bagaimana cara pengajuan libur cuti untuk pegawai?'))->toBeTrue()
        ->and($isLeaveClarification->invoke($controller, 'Saya ingin mengajukan cuti sakit'))->toBeFalse()
        ->and($isLeaveQuestion->invoke($controller, 'Bagaimana cara pengajuan libur cuti untuk pegawai?'))->toBeTrue()
        ->and($isLeaveQuestion->invoke($controller, 'Apa prosedur izin libur pegawai?'))->toBeTrue()
        ->and($employmentStatusFromData->invoke($controller, ['jenis_peg' => 'PPPK']))->toBe('PPPK')
        ->and($employmentStatusFromData->invoke($controller, ['jenis_peg' => 'Pegawai Pemerintah dengan Perjanjian Kerja']))->toBe('PPPK')
        ->and($employmentStatusFromData->invoke($controller, ['jenis_peg' => 'PNS']))->toBe('PNS')
        ->and($employmentStatusFromData->invoke($controller, ['jenis_peg' => 'Non-PNS']))->toBeNull();
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
