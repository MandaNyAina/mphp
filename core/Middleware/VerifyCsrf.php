<?php

declare(strict_types=1);

namespace Mphp\Middleware;

use Mphp\Http\HttpException;
use Mphp\Http\Middleware;
use Mphp\Http\Request;
use Mphp\Http\Response;
use Mphp\Security\Csrf;

/**
 * Rejects unsafe requests (POST/PUT/PATCH/DELETE) without a valid token,
 * sent as the X-CSRF-Token header or a `_token` field.
 *
 * Requests carrying an Authorization header are skipped: browsers cannot attach
 * that header cross-site without a CORS preflight, so they are not CSRF-able.
 */
final class VerifyCsrf implements Middleware
{
    /** @param list<string> $except fnmatch() patterns of paths to skip, e.g. "/webhooks/*" */
    public function __construct(private readonly Csrf $csrf, private readonly array $except = []) {}

    #[\Override]
    public function handle(Request $request, callable $next, string ...$arguments): Response
    {
        if (!$request->isSafeMethod() && !$this->isExempt($request)) {
            $given = $request->header('x-csrf-token') ?? $request->input('_token');

            if (!$this->csrf->verify(is_string($given) ? $given : null)) {
                throw new HttpException(419, 'CSRF token mismatch.');
            }
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        if ($request->header('authorization') !== null) {
            return true;
        }

        return array_any($this->except, static fn(string $pattern): bool => fnmatch($pattern, $request->path));
    }
}
