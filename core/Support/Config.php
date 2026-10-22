<?php

declare(strict_types=1);

namespace Mphp\Support;

/** Loads config/*.php files; read them with dot notation: config('database.default'). */
final class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function __construct(string $directory)
    {
        // scandir, not glob(): glob() cannot read inside a PHAR.
        foreach (is_dir($directory) ? scandir($directory) : [] as $file) {
            if (str_ends_with($file, '.php')) {
                $this->items[basename($file, '.php')] = require rtrim($directory, '/') . '/' . $file;
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $target = &$this->items;

        foreach (explode('.', $key) as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }

        $target = $value;
    }
}
