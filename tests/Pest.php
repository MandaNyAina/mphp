<?php

declare(strict_types=1);

use Mphp\App;
use Mphp\Database\Connection;
use Mphp\Database\Migrator;
use Mphp\Http\HttpException;
use Mphp\Http\Request;
use Mphp\Http\Response;
use Mphp\Middleware\Throttle;
use Mphp\Security\Csrf;
use Mphp\View\Vite;

function tempDir(): string
{
    $dir = sys_get_temp_dir() . '/mphp-test-' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return $dir;
}

/** A fully booted app on a fresh in-memory SQLite database, with a stub Vite manifest and isolated throttle storage. */
function testApp(): App
{
    $app = new App(dirname(__DIR__));
    (new Migrator($app->container->get(Connection::class), dirname(__DIR__) . '/database/migrations'))->migrate();

    $public = tempDir();
    mkdir($public . '/build/.vite', 0777, true);
    file_put_contents($public . '/build/.vite/manifest.json', json_encode([
        'resources/js/app.ts' => ['file' => 'assets/app.js', 'css' => ['assets/app.css'], 'isEntry' => true],
    ]));

    $app->container->instance(Vite::class, new Vite($public));
    $app->container->instance(Throttle::class, new Throttle(tempDir()));

    return $app;
}

/**
 * Send a request through the whole kernel. Unsafe methods get the session's CSRF token
 * unless $csrf is false (so tests can prove the protection works).
 *
 * @param array<string, mixed> $body
 * @param array<string, string> $headers
 */
function call(App $app, string $method, string $uri, array $body = [], array $headers = [], bool $csrf = true): Response
{
    if ($csrf && !in_array($method, ['GET', 'HEAD'], true)) {
        $headers['X-CSRF-Token'] = $app->container->get(Csrf::class)->token();
    }

    return $app->handle(Request::create($method, $uri, $body, $headers));
}

/** @return array{0: int, 1: array<string, string>} status + headers of the HttpException thrown by $fn (200 if none) */
function httpStatus(Closure $fn): array
{
    try {
        $fn();
    } catch (HttpException $e) {
        return [$e->status, $e->headers];
    }

    return [200, []];
}
