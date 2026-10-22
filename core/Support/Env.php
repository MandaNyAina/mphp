<?php

declare(strict_types=1);

namespace Mphp\Support;

/** Reads .env files. Real environment variables always win over the file (12-factor). */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            self::$values[trim($key)] = self::parseValue(trim($value));
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? (getenv($key) !== false ? getenv($key) : null) ?? self::$values[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }

    public static function reset(): void
    {
        self::$values = [];
    }

    private static function parseValue(string $value): string
    {
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            $end = strpos($value, $quote, 1);

            return $end === false ? substr($value, 1) : substr($value, 1, $end - 1);
        }

        // Unquoted: strip a trailing " # comment".
        $hash = strpos($value, ' #');

        return $hash === false ? $value : rtrim(substr($value, 0, $hash));
    }
}
