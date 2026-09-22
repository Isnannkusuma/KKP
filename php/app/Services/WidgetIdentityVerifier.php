<?php

namespace App\Services;

/**
 * Format token identitas (dibuat oleh BACKEND situs lain, bukan browser):
 *
 *   payload   = base64url(json_encode(['nip' => '...', 'name' => '...', 'exp' => unix_timestamp]))
 *   signature = hash_hmac('sha256', payload, $secretKey)
 *   token     = "{payload}.{signature}"
 *
 * Contoh kode PHP untuk situs lain men-generate token ini didokumentasikan
 * di widget-integration-guide.md.
 */
class WidgetIdentityVerifier
{
    /**
     * @return array{nip: string, name: ?string}|null null kalau token tidak valid/kedaluwarsa
     */
    public function verify(?string $token, string $secretKey): ?array
    {
        if (empty($token) || ! str_contains($token, '.')) {
            return null;
        }

        [$payload, $signature] = explode('.', $token, 2);

        $expectedSignature = hash_hmac('sha256', $payload, $secretKey);

        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $decodedPayload = base64_decode(strtr($payload, '-_', '+/'), true);

        if ($decodedPayload === false) {
            return null;
        }

        $data = json_decode($decodedPayload, true);

        if (! is_array($data) || empty($data['nip']) || empty($data['exp'])) {
            return null;
        }

        if ((int) $data['exp'] < time()) {
            return null; // token kedaluwarsa
        }

        return [
            'nip' => (string) $data['nip'],
            'name' => isset($data['name']) ? (string) $data['name'] : null,
        ];
    }
}