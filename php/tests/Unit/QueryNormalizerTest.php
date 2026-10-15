<?php

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
