#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Entry point of mphp.phar (copied in as the archive stub by PharBuilder).
 *   php mphp.phar <command>       console (migrate, key:generate, routes, serve ...)
 *   php mphp.phar serve           web server; this very file is the router script
 */
Phar::mapPhar('mphp.phar');

$root = 'phar://mphp.phar';
$home = getenv('MPHP_HOME') ?: getcwd();

require $root . '/core/autoload.php';

if (PHP_SAPI === 'cli-server') {
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

    // Static files (built assets) live inside the archive: stream them, never dotfiles or PHP.
    if ($path !== '/' && !preg_match('#(^|/)\.|\.php$|\x00#', $path) && is_file($root . '/public' . $path)) {
        $types = [
            'css' => 'text/css', 'js' => 'text/javascript', 'mjs' => 'text/javascript', 'json' => 'application/json',
            'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp', 'gif' => 'image/gif', 'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
        ];
        header('Content-Type: ' . ($types[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: ' . (str_starts_with($path, '/build/') ? 'public, max-age=31536000, immutable' : 'public, max-age=3600'));
        readfile($root . '/public' . $path);
        exit;
    }

    (new Mphp\App($root, $home))->run();
    exit;
}

exit((new Mphp\Console\Console(new Mphp\App($root, $home)))->run($argv));

__HALT_COMPILER();
