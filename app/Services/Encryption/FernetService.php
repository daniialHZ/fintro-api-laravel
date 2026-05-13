<?php

namespace App\Services\Encryption;

use RuntimeException;

class FernetService
{
    private const SALT = 'financial_dashboard_salt_2024';
    private const VERSION = "\x80";

    public function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        [$signingKey, $encryptionKey] = $this->splitKey();
        $iv = random_bytes(16);
        $timestamp = time();
        $packedTimestamp = pack('N2', ($timestamp >> 32) & 0xffffffff, $timestamp & 0xffffffff);
        $ciphertext = openssl_encrypt($value, 'AES-128-CBC', $encryptionKey, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt value.');
        }

        $payload = self::VERSION.$packedTimestamp.$iv.$ciphertext;
        $signature = hash_hmac('sha256', $payload, $signingKey, true);

        return $this->base64UrlEncode($payload.$signature);
    }

    public function decrypt(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $decoded = $this->base64UrlDecode($token);
        } catch (\Throwable) {
            return null;
        }

        if (strlen($decoded) < 57 || $decoded[0] !== self::VERSION) {
            return null;
        }

        [$signingKey, $encryptionKey] = $this->splitKey();
        $payload = substr($decoded, 0, -32);
        $signature = substr($decoded, -32);
        $expected = hash_hmac('sha256', $payload, $signingKey, true);

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $iv = substr($decoded, 9, 16);
        $ciphertext = substr($decoded, 25, -32);
        $plaintext = openssl_decrypt($ciphertext, 'AES-128-CBC', $encryptionKey, OPENSSL_RAW_DATA, $iv);

        return $plaintext === false ? null : $plaintext;
    }

    private function splitKey(): array
    {
        $secret = (string) env('ENCRYPTION_SECRET', '');
        $key = hash_pbkdf2('sha256', $secret, self::SALT, 100000, 32, true);

        return [substr($key, 0, 16), substr($key, 16, 16)];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }
}
