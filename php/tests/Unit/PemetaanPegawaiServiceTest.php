<?php

use App\Services\PemetaanPegawaiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('retrieves employee data from the pemetaan endpoint envelope', function () {
    $endpoint = 'https://sianter.kkp.go.id/api/pemetaan/detail?nip=';
    $nip = '195909301979121001';

    putenv("PEMETAAN_API_KEY={$endpoint}");

    Http::fake(function (Request $request) use ($endpoint, $nip) {
        expect($request->url())->toBe($endpoint . $nip);

        return Http::response([
            'success' => true,
            'data' => [
                'nip' => $nip,
                'total_sumbu' => '121.25',
                'box' => '2',
            ],
        ]);
    });

    expect((new PemetaanPegawaiService)->getPegawaiByNip($nip))
        ->toBe([
            'nip' => $nip,
            'total_sumbu' => '121.25',
            'box' => '2',
        ]);
});

it('returns null when the pemetaan API reports failure', function () {
    $nip = '196103011986031004';

    putenv('PEMETAAN_API_KEY=https://sianter.kkp.go.id/api/pemetaan/detail?nip=');

    Http::fake(Http::response([
        'success' => false,
        'data' => null,
    ]));

    expect((new PemetaanPegawaiService)->getPegawaiByNip($nip))
        ->toBeNull();
});
