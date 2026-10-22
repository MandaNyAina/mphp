<?php

declare(strict_types=1);

namespace Mphp\Security;

use RuntimeException;

/**
 * Authenticated encryption (libsodium XSalsa20-Poly1305) with a fresh random nonce per message.
 * Replaces the old AES-CTR + hard-coded IV helpers.
 */
final class Crypto
{
    private readonly string $key;

    public function __construct(string $appKey)
    {
        $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : false;

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('APP_KEY is missing or invalid. Run: php bin/mphp key:generate');
        }

        $this->key = $raw;
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $this->key);

        return rtrim(strtr(base64_encode($nonce . $cipher), '+/', '-_'), '=');
    }

    /** Returns null when the payload is malformed or has been tampered with. */
    public function decrypt(string $payload): ?string
    {
        $raw = base64_decode(strtr($payload, '-_', '+/'), true);

        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return null;
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );

        return $plain === false ? null : $plain;
    }

    /** Keyed signature, e.g. for signed URLs or cookies. */
    public function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->key);
    }

    public function verifySignature(string $data, string $signature): bool
    {
        return hash_equals($this->sign($data), $signature);
    }

    /** Cryptographically secure, unbiased random string. */
    public static function randomString(int $length, bool $symbols = false): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' . ($symbols ? '.*,#^$!-:;=+_%' : '');
        $max = strlen($alphabet) - 1;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $max)];
        }

        return $result;
    }
}
