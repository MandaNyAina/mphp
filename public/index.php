<?php

declare(strict_types=1);

// `php bin/mphp serve` uses this file as the router script: let real static files through.
if (PHP_SAPI === 'cli-server') {
    $requested = realpath(__DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)));

    if ($requested !== false && str_starts_with($requested, __DIR__ . DIRECTORY_SEPARATOR) && !str_ends_with($requested, '.php') && is_file($requested)) {
        return false;
    }
}

$base = dirname(__DIR__);

// Composer is optional at runtime: core/autoload.php is a drop-in replacement.
require is_file($base . '/vendor/autoload.php') ? $base . '/vendor/autoload.php' : $base . '/core/autoload.php';

(new Mphp\App($base))->run();
