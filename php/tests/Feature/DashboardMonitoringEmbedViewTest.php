<?php

test('standalone dashboard monitoring view renders metrics and charts without excluded actions', function () {
    $html = view('components.dashboard-monitoring-embed', [
        'period' => 30,
        'stats' => [
            'total' => 1488,
            'answered' => 1219,
            'unanswered' => 267,
        ],
        'daily' => [
            ['date' => '2026-10-05', 'label' => '05 Oct', 'total' => 12, 'answered' => 10, 'unanswered' => 2],
            ['date' => '2026-10-06', 'label' => '06 Oct', 'total' => 18, 'answered' => 15, 'unanswered' => 3],
        ],
    ])->render();

    expect($html)
        ->toContain('Monitoring Chatbot')
        ->toContain('Total pertanyaan')
        ->toContain('Tingkat resolusi')
        ->toContain('Pertanyaan belum terjawab')
        ->toContain('Aktivitas pertanyaan')
        ->toContain('Resolusi per hari')
        ->toContain('name="period"')
        ->not->toContain('Unduh laporan')
        ->not->toContain('Hapus data')
        ->not->toContain('Buka Chatbot');
});
