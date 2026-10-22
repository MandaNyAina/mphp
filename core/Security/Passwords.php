<?php

declare(strict_types=1);

namespace Mphp\Security;

/** Thin wrapper over password_* so the algorithm can be upgraded in one place. */
final class Passwords
{
    public static function hash(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /** After a successful login, re-hash when this returns true and store the new hash. */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    /** Length beats composition rules (NIST 800-63B). 1024 max avoids hashing-DoS. */
    public static function isStrong(string $password, int $minLength = 12): bool
    {
        $length = mb_strlen($password);

        return $length >= $minLength && $length <= 1024;
    }
}
