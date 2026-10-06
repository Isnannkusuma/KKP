<?php

use App\Services\BangkomPegawaiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('retrieves employee development data from the bangkom endpoint envelope', function () {
    $endpoint = 'https://sianter.kkp.go.id/api/bangkom/detail?nip=';
    $nip = '195909301979121001';

    putenv("BANGKOM_API_KEY={$endpoint}");

    Http::fake(function (Request $request) use ($endpoint, $nip) {
        expect($request->url())->toBe($endpoint.$nip);

        return Http::response([
            'success' => true,
            'data' => [
                'nip' => $nip,
                'total_sumbu' => '112.50',
                'box' => '3',
                'pengembangan_kompetensi' => 'Sertifikasi kompetensi',
            ],
        ]);
    });

    expect((new BangkomPegawaiService)->getBangkomByNip($nip))
        ->toBe([
            'nip' => $nip,
            'total_sumbu' => '112.50',
            'box' => '3',
            'pengembangan_kompetensi' => 'Sertifikasi kompetensi',
        ]);
});

it('returns null when the bangkom API reports failure', function () {
    $nip = '196103011986031004';

    putenv('BANGKOM_API_KEY=https://sianter.kkp.go.id/api/bangkom/detail?nip=');

    Http::fake(Http::response([
        'success' => false,
        'data' => null,
    ]));

    expect((new BangkomPegawaiService)->getBangkomByNip($nip))->toBeNull();
});
