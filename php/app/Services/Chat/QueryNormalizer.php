<?php

namespace App\Services\Chat;

class QueryNormalizer
{
    public static function normalize(string $query): string
    {
        $normalized = preg_replace(
            '/(?<![\p{L}\p{N}_])(?:asn[\s-]+cpns|cpns[\s-]+asn|asn|cpns)(?![\p{L}\p{N}_])/iu',
            'asn cpns',
            trim($query),
        );

        if ($normalized === null) {
            throw new \RuntimeException('Unable to normalize the search query.');
        }

        return mb_strtolower($normalized);
    }
}
