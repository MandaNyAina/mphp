<?php

declare(strict_types=1);

namespace Mphp\Middleware;

use Mphp\Http\Middleware;
use Mphp\Http\Request;
use Mphp\Http\Response;
use Mphp\View\Vite;

/** Adds a strict CSP and the usual hardening headers to every response. */
final class SecurityHeaders implements Middleware
{
    private const CSP = [
        'default-src' => "'self'",
        'script-src' => "'self'",
        'style-src' => "'self'",
        'img-src' => "'self' data:",
        'font-src' => "'self'",
        'connect-src' => "'self'",
        'object-src' => "'none'",
        'base-uri' => "'self'",
        'form-action' => "'self'",
        'frame-ancestors' => "'none'",
    ];

    public function __construct(private readonly Vite $vite) {}

    #[\Override]
    public function handle(Request $request, callable $next, string ...$arguments): Response
    {
        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->policy(),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($request->secure) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response = $response->withHeaderIfMissing($name, $value);
        }

        return $response;
    }

    private function policy(): string
    {
        $policy = self::CSP;

        // `npm run dev`: allow the Vite dev server (HMR over WebSocket, injected <style>).
        if (($dev = $this->vite->devServerUrl()) !== null) {
            $socket = preg_replace('#^http#', 'ws', $dev);
            $policy['script-src'] .= " $dev";
            $policy['style-src'] .= " $dev 'unsafe-inline'";
            $policy['connect-src'] .= " $dev $socket";
            $policy['img-src'] .= " $dev";
            $policy['font-src'] .= " $dev";
        }

        return implode('; ', array_map(static fn(string $k, string $v): string => "$k $v", array_keys($policy), $policy));
    }
}
