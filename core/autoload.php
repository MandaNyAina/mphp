<?php

declare(strict_types=1);

/**
 * Zero-dependency PSR-4 autoloader.
 *
 * In production the framework needs no Composer: public/index.php falls back to
 * this file when vendor/autoload.php is absent, so a release is just a folder to upload.
 */
spl_autoload_register(static function (string $class): void {
    $map = ['Mphp\\' => __DIR__ . '/', 'App\\' => __DIR__ . '/../app/'];

    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});

require_once __DIR__ . '/helpers.php';
