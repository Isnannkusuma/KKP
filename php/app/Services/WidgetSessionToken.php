<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Token sesi login KHUSUS widget (beda dari sesi login web utama).
 * Dipakai supaya widget bisa punya status login sendiri (NIP + password),
 * tanpa bergantung pada cookie session (yang tidak reliable lintas domain
 * saat widget ditanam di iframe situs lain).
 *
 * Token ini dienkripsi pakai APP_KEY aplikasi sendiri (Laravel Crypt),
 * jadi hanya bisa dibuat & diverifikasi oleh backend kita sendiri - tidak
 * perlu skema HMAC lintas pihak seperti token identitas SSO.
 */
class WidgetSessionToken
{
    public function issue(string $nip, ?string $name, int $ttlSeconds = 3600): string
    {
        return Crypt::encryptString(json_encode([
            'nip' => $nip,
            'name' => $name,
            'exp' => time() + $ttlSeconds,
        ]));
    }

    /**
     * @return array{nip: string, name: ?string}|null
     */
    public function verify(?string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

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
