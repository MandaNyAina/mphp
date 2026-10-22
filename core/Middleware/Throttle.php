<?php

declare(strict_types=1);

namespace Mphp\Middleware;

use Mphp\Http\HttpException;
use Mphp\Http\Middleware;
use Mphp\Http\Request;
use Mphp\Http\Response;

/**
 * Fixed-window rate limiter stored in plain files (no Redis/APCu needed).
 * Use as "throttle:5,60" => 5 requests per 60 seconds per IP and path.
 */
final class Throttle implements Middleware
{
    public function __construct(private readonly string $directory) {}

    #[\Override]
    public function handle(Request $request, callable $next, string ...$arguments): Response
    {
        $max = max(1, (int) ($arguments[0] ?? 60));
        $window = max(1, (int) ($arguments[1] ?? 60));
        $now = time();

        [$count, $resetAt] = $this->hit(sha1($request->ip() . '|' . $request->method . '|' . $request->path), $window, $now);

        if ($count > $max) {
            throw new HttpException(429, 'Too many requests.', ['Retry-After' => (string) max(1, $resetAt - $now)]);
        }

        return $next($request)
            ->withHeader('X-RateLimit-Limit', (string) $max)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $max - $count));
    }

    /** @return array{0: int, 1: int} [hits in current window, window end timestamp] */
    private function hit(string $key, int $window, int $now): array
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        $handle = fopen($this->directory . '/' . $key, 'c+');
        if ($handle === false) {
            return [1, $now + $window]; // fail open: a broken cache dir must not take the site down
        }

        flock($handle, LOCK_EX);
        [$count, $resetAt] = array_map('intval', explode('|', (string) stream_get_contents($handle)) + [0, 0]);

        if ($resetAt <= $now) {
            [$count, $resetAt] = [0, $now + $window];
        }
        $count++;

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, "$count|$resetAt");
        flock($handle, LOCK_UN);
        fclose($handle);

        if (random_int(1, 100) === 1) {
            $this->purge($now);
        }

        return [$count, $resetAt];
    }

    private function purge(int $now): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $now - 86400) {
                @unlink($file);
            }
        }
    }
}
