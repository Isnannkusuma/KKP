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
