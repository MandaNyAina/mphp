<?php

declare(strict_types=1);

namespace Mphp\Http;

interface Middleware
{
    /**
     * @param callable(Request): Response $next
     * @param string ...$arguments values after the colon in "throttle:5,60"
     */
    public function handle(Request $request, callable $next, string ...$arguments): Response;
}
