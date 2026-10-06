<?php

use App\Services\PortalPegawaiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('retrieves employee data from the configured portal endpoint', function () {
    $nip = '197008191995031001';

    Http::fake(function (Request $request) use ($nip) {
        expect($request->url())
            ->toEndWith('/drh/'.$nip.'/');
        expect($request->header('Authorization'))
            ->not->toBeEmpty();

        return Http::response([
            'data' => [
                'nip' => $nip,
                'nama' => 'Nama dari Portal',
            ],
        ]);
    });

    $pegawai = (new PortalPegawaiService)->getPegawaiByNip($nip);

    expect($pegawai)->toBe([
        'nip' => $nip,
        'nama' => 'Nama dari Portal',
    ]);
});

it('returns null when the portal request fails', function () {
    Http::fake(Http::response([], 503));

    expect((new PortalPegawaiService)->getPegawaiByNip('197008191995031001'))
        ->toBeNull();
});

it('extracts employee data from the portal result envelope', function () {
    $nip = '196103011986031004';

    Http::fake(Http::response([
        'success' => true,
        'message' => null,
        'result' => [
            'nip' => $nip,
            'nama' => 'Nama dari Portal',
        ],
    ]));

    expect((new PortalPegawaiService)->getPegawaiByNip($nip))
        ->toMatchArray([
            'nip' => $nip,
            'nama' => 'Nama dari Portal',
        ]);
});
