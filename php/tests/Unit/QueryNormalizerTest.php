<?php

use App\Models\ChatbotSetting;
use App\Services\Chat\QueryNormalizer;

test('ASN and CPNS questions normalize to the same search query', function () {
    expect(QueryNormalizer::normalize('kapan seleksi CPNS dimulai?'))
        ->toBe(QueryNormalizer::normalize('Kapan seleksi ASN dimulai?'))
        ->toBe('kapan seleksi asn cpns dimulai?');
});

test('ASN and CPNS are normalized only as complete terms', function () {
    expect(QueryNormalizer::normalize('informasi CPNS terkait ASN'))
        ->toBe('informasi asn cpns terkait asn cpns')
        ->and(QueryNormalizer::normalize('pegawai negeri'))
        ->toBe('pegawai negeri');
});

test('SDMAO and Permen KP acronyms are expanded', function () {
    expect(QueryNormalizer::normalize('Fungsi Biro SDMAO'))
        ->toBe('fungsi biro sumber daya manusia aparatur dan organisasi')
        ->and(QueryNormalizer::normalize('Permen KP nomor 2 tahun 2025'))
        ->toBe('peraturan menteri kelautan dan perikanan nomor 2 tahun 2025');
});

test('configured chat shortcuts expand only as complete words', function () {
    ChatbotSetting::create([
        'query_aliases' => [
            'bsk' => 'besok',
            'kpn' => 'kapan',
            'brp' => 'berapa',
        ],
    ]);

    expect(QueryNormalizer::normalize('BSK, kpn jadwal? Brp pegawai?'))
        ->toBe('besok, kapan jadwal? berapa pegawai?')
        ->and(QueryNormalizer::normalize('jadwalbsk bsk2 xbsk'))
        ->toBe('jadwalbsk bsk2 xbsk');
});

test('configured shortcuts are expanded before question intent checks', function () {
    ChatbotSetting::create([
        'query_aliases' => ['brp' => 'berapa'],
    ]);

    expect(QueryNormalizer::requestsCompleteList('brp pegawai dilantik?'))->toBeTrue()
        ->and(QueryNormalizer::requestedListCount('berikan 10 pegawai, brp nama?'))->toBe(10);
});

test('complete-list questions are identified for expanded document retrieval', function () {
    expect(QueryNormalizer::requestsCompleteList(
        'siapa saja pegawai yang dilantik pada tanggal 16 oktober 2026?'
    ))->toBeTrue()
        ->and(QueryNormalizer::requestsCompleteList('siapa saja pegawai yang di lantik hari ini?'))
        ->toBeTrue()
        ->and(QueryNormalizer::requestsCompleteList('berikan 10 pegawai yang dilantik hari ini tanggal 16 oktober'))
        ->toBeTrue()
        ->and(QueryNormalizer::requestsCompleteList('siapa yang dilantik pada jabatan Direktur Jenderal Penguatan Daya Saing Produk Kelautan dan Perikanan?'))
        ->toBeTrue()
        ->and(QueryNormalizer::requestsCompleteList('daftar seluruh pejabat yang dilantik'))
        ->toBeTrue()
        ->and(QueryNormalizer::requestsCompleteList('siapa yang bertanggung jawab atas pelantikan?'))
        ->toBeFalse()
        ->and(QueryNormalizer::requestsCompleteList('siapa nama chatbot ini?'))
        ->toBeFalse()
        ->and(QueryNormalizer::requestsCompleteList('kapan pelantikan dilaksanakan?'))
        ->toBeFalse();
});

test('requested list count supports numerals and Indonesian number words', function () {
    expect(QueryNormalizer::requestedListCount('berikan 10 pegawai yang dilantik'))
        ->toBe(10)
        ->and(QueryNormalizer::requestedListCount('berikan nama 10 pegawai yang dilantik'))
        ->toBe(10)
        ->and(QueryNormalizer::requestedListCount('berikan 10 nama pegawai yang dilantik'))
        ->toBe(10)
        ->and(QueryNormalizer::requestedListCount(
            'berikan 10 nama pegawai yang dilantik, sekarang berikan 30 nama'
        ))
        ->toBe(30)
        ->and(QueryNormalizer::requestedListCount('tampilkan sepuluh orang yang dilantik'))
        ->toBe(10)
        ->and(QueryNormalizer::requestedListCount('berikan dua belas pegawai'))
        ->toBe(12)
        ->and(QueryNormalizer::requestedListCount('siapa saja pegawai yang dilantik?'))
        ->toBeNull();
});

test('relative today is expanded for complete-list retrieval queries', function () {
    expect(QueryNormalizer::resolveRelativeDate(
        'berikan 10 nama pegawai yang dilantik pada hari ini',
        '16 Oktober 2026',
    ))->toBe('berikan 10 nama pegawai yang dilantik pada 16 Oktober 2026')
        ->and(QueryNormalizer::resolveRelativeDate(
            'bagaimana kebijakan cuti pada hari ini',
            '16 Oktober 2026',
        ))->toBe('bagaimana kebijakan cuti pada hari ini');
});

test('FAQ shortcut rejects questions with unrelated topics', function () {
    expect(QueryNormalizer::hasMeaningfulFaqOverlap(
        'mohon info tugas dan fungsi Biro SDM Aparatur dan Organisasi',
        'rekomendasi menu makan siang sehat untuk pegawai'
    ))->toBeFalse();
});

test('FAQ shortcut accepts questions sharing their subject after acronym expansion', function () {
    expect(QueryNormalizer::hasMeaningfulFaqOverlap(
        'mohon info tugas dan fungsi Biro SDMAO',
        'apa tugas dan fungsi Biro Sumber Daya Manusia Aparatur dan Organisasi'
    ))->toBeTrue();
});
