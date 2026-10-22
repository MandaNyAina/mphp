<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'M-PHP'),

    // "production" unless .env says otherwise: safe by default.
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),

    // php bin/mphp key:generate
    'key' => env('APP_KEY', ''),

    // Use storage/cache/routes.php when present (php bin/mphp routes:cache). Off while debugging.
    'route_cache' => env('ROUTE_CACHE', !env('APP_DEBUG', false)),

    // Set to true only behind a reverse proxy you control (reads X-Forwarded-Proto).
    'trust_proxy' => env('TRUST_PROXY', false),

    'session_name' => 'mphp_session',

    // Global middleware, applied to every route. Aliases: csrf, throttle:max,seconds
    'middleware' => ['csrf'],

    // Paths exempt from CSRF checks (fnmatch patterns), e.g. incoming webhooks.
    'csrf_except' => [],
];
