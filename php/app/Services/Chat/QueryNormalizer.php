<?php

namespace App\Services\Chat;

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
        $normalized = trim($query);

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

        if ($normalized === null) {
            throw new \RuntimeException('Unable to normalize the search query.');
        }

        return mb_strtolower($normalized);
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
