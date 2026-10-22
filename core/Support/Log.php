<?php

declare(strict_types=1);

namespace Mphp\Support;

/** Minimal daily-file logger. Never throws: logging must not take the app down. */
final class Log
{
    private static ?string $directory = null;

    public static function setDirectory(string $directory): void
    {
        self::$directory = rtrim($directory, '/');
    }

    /** @param array<string, mixed> $context */
    public static function write(string $level, string $message, array $context = []): void
    {
        if (self::$directory === null) {
            return;
        }

        if (!is_dir(self::$directory)) {
            @mkdir(self::$directory, 0775, true);
        }

        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            str_replace(["\r", "\n"], ' ', $message),
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : '',
        );

        @file_put_contents(self::$directory . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }
}
