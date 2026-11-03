<?php

namespace App\Services\Chat;

use App\Models\ChatbotSetting;

class QueryNormalizer
{
    private const FAQ_STOP_WORDS = [
        'ada', 'agar', 'akan', 'apa', 'apakah', 'atas', 'atau', 'bagaimana',
        'bagi', 'dan', 'dari', 'dengan', 'di', 'dalam', 'dapat', 'dijelaskan',
        'info', 'informasi', 'ini', 'itu', 'jelaskan', 'ke', 'kepada', 'mohon',
        'pada', 'saja', 'saya', 'sebagai', 'sebutkan', 'tentang', 'terkait',
        'tidak', 'untuk', 'yang',
    ];

    public static function normalize(string $query): string
    {
        $normalized = self::expandShortcuts(trim($query));

        // Expand Biro SDMAO acronym so keyword & semantic search match legal regulation texts
        $normalized = preg_replace(
            '/\b(?:biro\s+)?sdmao\b/iu',
            'Biro Sumber Daya Manusia Aparatur dan Organisasi',
            $normalized
        ) ?? $normalized;

        // Expand Permen KP acronym if needed
        $normalized = preg_replace(
            '/\bpermen\s+kp\b/iu',
            'Peraturan Menteri Kelautan dan Perikanan',
            $normalized
        ) ?? $normalized;

        $normalized = preg_replace(
            '/(?<![\p{L}\p{N}_])(?:asn[\s-]+cpns|cpns[\s-]+asn|asn|cpns)(?![\p{L}\p{N}_])/iu',
            'asn cpns',
            $normalized,
        );

        $normalized = preg_replace(
            '/\b(?:izin\s+)?libur(?:\s+cuti)?\b/iu',
            'cuti',
            $normalized,
        ) ?? $normalized;

        if ($normalized === null) {
            throw new \RuntimeException('Unable to normalize the search query.');
        }

        return mb_strtolower($normalized);
    }

    /**
     * Expand admin-configured shorthand only when it appears as a complete word.
     *
     * @param  array<string, string>|null  $aliases
     */
    public static function expandShortcuts(string $query, ?array $aliases = null): string
    {
        $aliases ??= ChatbotSetting::queryAliases();
        if ($aliases === []) {
            return $query;
        }

        $normalizedAliases = [];
        foreach ($aliases as $shortcut => $expansion) {
            if (is_string($shortcut) && is_string($expansion) && $shortcut !== '') {
                $normalizedAliases[mb_strtolower($shortcut)] = $expansion;
            }
        }

        if ($normalizedAliases === []) {
            return $query;
        }

        uksort($normalizedAliases, static fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        $pattern = '/(?<![\p{L}\p{N}_])(?:'
            .implode('|', array_map(
                static fn (string $shortcut): string => preg_quote($shortcut, '/'),
                array_keys($normalizedAliases)
            ))
            .')(?![\p{L}\p{N}_])/iu';

        return preg_replace_callback(
            $pattern,
            static fn (array $matches): string => $normalizedAliases[mb_strtolower($matches[0])] ?? $matches[0],
            $query
        ) ?? $query;
    }

    public static function requestsCompleteList(string $query): bool
    {
        $normalized = mb_strtolower(self::expandShortcuts($query));
        $hasAppointmentTopic = preg_match(
            '/\b(dilantik|di[\s-]*lantik|pelantikan|melantik|pengangkatan)\b/u',
            $normalized
        ) === 1;
        $hasPeopleTopic = preg_match(
            '/\b(pegawai|pejabat|orang|nama(?:-nama)?|jabatan|direktur|kepala|inspektur)\b/u',
            $normalized
        ) === 1;
        $hasListRequest = preg_match(
            '/\b(siapa\s+saja|mana\s+saja|apa\s+saja|daftar|semua|seluruh|nama(?:-nama)?|sebutkan|berikan|beri|tampilkan|tunjukkan|berapa)\b/u',
            $normalized
        ) === 1;
        $asksAssistantIdentity = preg_match('/\b(bot|chatbot|asisten|anda|kamu)\b/u', $normalized) === 1
            && preg_match('/\b(pegawai|pejabat|orang|jabatan|direktur|kepala|inspektur)\b/u', $normalized) !== 1;
        $asksWho = preg_match('/\bsiapa\b/u', $normalized) === 1;
        $hasAppointingAction = preg_match('/\b(dilantik|di[\s-]*lantik|melantik)\b/u', $normalized) === 1;

        return ! $asksAssistantIdentity
            && (($hasAppointingAction && ($hasPeopleTopic || $asksWho))
                || ($hasAppointmentTopic && $hasPeopleTopic && $hasListRequest)
                || ($hasListRequest && $hasPeopleTopic));
    }

    public static function resolveRelativeDate(string $query, string $today): string
    {
        if (! self::requestsCompleteList($query)) {
            return $query;
        }

        return preg_replace('/\bhari\s+ini\b/iu', $today, $query) ?? $query;
    }

    public static function requestedListCount(string $query): ?int
    {
        $query = self::expandShortcuts($query);
        $numberWords = [
            'satu' => 1,
            'dua' => 2,
            'tiga' => 3,
            'empat' => 4,
            'lima' => 5,
            'enam' => 6,
            'tujuh' => 7,
            'delapan' => 8,
            'sembilan' => 9,
            'sepuluh' => 10,
            'sebelas' => 11,
            'dua belas' => 12,
            'tiga belas' => 13,
            'empat belas' => 14,
            'lima belas' => 15,
            'enam belas' => 16,
            'tujuh belas' => 17,
            'delapan belas' => 18,
            'sembilan belas' => 19,
            'dua puluh' => 20,
        ];
        $numberPattern = '\d{1,2}|'.implode('|', array_map(
            static fn (string $word): string => preg_quote($word, '/'),
            array_keys($numberWords)
        ));

        if (preg_match_all('/\b('.$numberPattern.')\s+(?:orang|pegawai|pejabat|nama)\b/iu', $query, $matches) === 0) {
            return null;
        }

        $requestedCount = end($matches[1]);
        if (! is_string($requestedCount)) {
            return null;
        }

        $count = ctype_digit($requestedCount)
            ? (int) $requestedCount
            : $numberWords[mb_strtolower($requestedCount)];

        return $count > 0 ? min($count, 50) : null;
    }

    public static function hasMeaningfulFaqOverlap(string $query, string $faqQuestion): bool
    {
        $tokens = static function (string $text): array {
            $normalized = self::normalize($text);
            $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return array_values(array_unique(array_filter(
                $parts,
                fn (string $part): bool => mb_strlen($part) > 2
                    && ! in_array($part, self::FAQ_STOP_WORDS, true)
            )));
        };

        $queryTokens = $tokens($query);
        $faqTokens = $tokens($faqQuestion);
        $requiredOverlap = min(2, min(count($queryTokens), count($faqTokens)));

        if ($requiredOverlap === 0) {
            return false;
        }

        return count(array_intersect($queryTokens, $faqTokens)) >= $requiredOverlap;
    }
}
