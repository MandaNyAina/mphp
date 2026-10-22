<?php

declare(strict_types=1);

describe('pages', function () {
    it('serves the Svelte shell with props, CSRF token and hardening headers', function () {
        $response = call(testApp(), 'GET', '/');

        expect($response->status)->toBe(200)
            ->and($response->body)->toContain('id="mphp-props"')
            ->and($response->body)->toContain('"appName"')
            ->and($response->body)->toContain('/build/assets/app.js')
            ->and($response->header('Content-Security-Policy'))->toContain("default-src 'self'")->toContain("frame-ancestors 'none'")
            ->and($response->header('X-Frame-Options'))->toBe('DENY')
            ->and($response->header('X-Content-Type-Options'))->toBe('nosniff');
    });

    it('answers HEAD without a body', function () {
        $response = call(testApp(), 'HEAD', '/');

        expect($response->status)->toBe(200)->and($response->body)->toBe('');
    });

    it('renders an HTML 404 for browsers and JSON for API callers, both with security headers', function () {
        $app = testApp();

        $html = call($app, 'GET', '/nope', headers: ['Accept' => 'text/html']);
        $json = call($app, 'GET', '/api/nope');

        expect($html->status)->toBe(404)->and($html->body)->toContain('Page not found')->and($html->header('X-Frame-Options'))->toBe('DENY')
            ->and($json->status)->toBe(404)->and(json_decode($json->body, true))->toBe(['message' => 'Not found.', 'status' => 404]);
    });

    it('hides internals when an unexpected error happens in production', function () {
        $app = testApp();
        $app->router->get('/boom', fn() => throw new RuntimeException('database password is hunter2'));

        $html = call($app, 'GET', '/boom', headers: ['Accept' => 'text/html']);
        $json = call($app, 'GET', '/boom', headers: ['Accept' => 'application/json']);

        expect($html->status)->toBe(500)->and($html->body)->not->toContain('hunter2')
            ->and($json->status)->toBe(500)->and($json->body)->not->toContain('hunter2')->and($json->body)->toContain('Server error.');
    });
});

describe('debug bar', function () {
    it('is absent in production', function () {
        $response = call(testApp(), 'GET', '/');

        expect($response->body)->not->toContain('mphp-debug')->and($response->header('Server-Timing'))->toBeNull();
    });

    it('shows status, route and every SQL query on HTML pages when APP_DEBUG is on', function () {
        $app = testApp();
        $app->config->set('app.debug', true);

        $response = call($app, 'GET', '/');

        expect($response->body)->toContain('id="mphp-debug"')
            ->and($response->body)->toContain('home HomeController@index')
            ->and($response->body)->toContain('SELECT * FROM &quot;names&quot;')
            ->and($response->body)->toContain('1 queries')
            ->and($response->header('Server-Timing'))->toMatch('/^app;dur=[\d.]+, db;dur=[\d.]+;desc="1 queries"$/');
    });

    it('flags repeated queries (N+1) and times API calls through Server-Timing only', function () {
        $app = testApp();
        $app->config->set('app.debug', true);
        $app->router->get('/n-plus-one', function () use ($app) {
            foreach (range(1, 3) as $i) {
                $app->container->get(Mphp\Database\Connection::class)->select('SELECT 1 AS x');
            }

            return Mphp\Http\Response::html('<html><body>x</body></html>');
        });

        $page = call($app, 'GET', '/n-plus-one');
        $api = call($app, 'GET', '/api/names');

        expect($page->body)->toContain('repeated queries')->and($page->body)->toContain('ran 3×')
            ->and($api->body)->not->toContain('mphp-debug')->and($api->header('Server-Timing'))->not->toBeNull();
    });

    it('serves its stylesheet under the strict CSP', function () {
        $app = testApp();
        $app->config->set('app.debug', true);

        $css = call($app, 'GET', '/_mphp/bar.css');

        expect($css->status)->toBe(200)->and($css->header('Content-Type'))->toContain('text/css')
            ->and($css->header('Content-Security-Policy'))->toContain("style-src 'self'");
    });
});

describe('names API', function () {
    it('rejects unsafe requests without a CSRF token', function () {
        $response = call(testApp(), 'POST', '/api/names', ['firstname' => 'Ada'], csrf: false);

        expect($response->status)->toBe(419);
    });

    it('lets token-less requests through only when they carry an Authorization header', function () {
        $response = call(testApp(), 'POST', '/api/names', ['firstname' => 'Ada'], ['Authorization' => 'Bearer t'], csrf: false);

        expect($response->status)->toBe(201);
    });

    it('creates, lists, updates and deletes', function () {
        $app = testApp();

        $created = call($app, 'POST', '/api/names', ['firstname' => 'Ada']);
        $id = json_decode($created->body, true)['id'];

        expect($created->status)->toBe(201)->and(json_decode($created->body, true))->toMatchArray(['firstname' => 'Ada'])
            ->and(json_decode(call($app, 'GET', '/api/names')->body, true))->toHaveCount(1);

        $updated = call($app, 'PUT', "/api/names/$id", ['firstname' => 'Grace']);
        expect($updated->status)->toBe(200)->and(json_decode($updated->body, true)['firstname'])->toBe('Grace');

        $deleted = call($app, 'DELETE', "/api/names/$id");
        expect($deleted->status)->toBe(204)->and($deleted->body)->toBe('')
            ->and(json_decode(call($app, 'GET', '/api/names')->body, true))->toBe([]);
    });

    it('ignores fields that are not fillable (mass-assignment)', function () {
        $app = testApp();

        $body = json_decode(call($app, 'POST', '/api/names', ['firstname' => 'Ada', 'id' => 999, 'created_at' => '1999-01-01'])->body, true);

        expect($body['id'])->toBe(1)->and($body['createdAt'])->not->toStartWith('1999');
    });

    it('answers 422 with per-field errors, 404 for unknown rows and 405 for wrong methods', function () {
        $app = testApp();

        $invalid = call($app, 'POST', '/api/names', ['firstname' => '']);
        $missing = call($app, 'PUT', '/api/names/42', ['firstname' => 'x']);
        $wrongMethod = call($app, 'PATCH', '/api/names');
        $malformed = $app->handle(new Mphp\Http\Request('POST', '/api/names', headers: ['x-csrf-token' => $app->container->get(Mphp\Security\Csrf::class)->token()], bodyError: 'Malformed JSON body.'));

        expect($invalid->status)->toBe(422)->and(json_decode($invalid->body, true)['errors'])->toHaveKey('firstname')
            ->and($missing->status)->toBe(404)
            ->and($wrongMethod->status)->toBe(405)->and($wrongMethod->header('Allow'))->toContain('GET')->toContain('POST')
            ->and($malformed->status)->toBe(400);
    });

    it('rate limits creation to 30 requests per minute', function () {
        $app = testApp();
        $statuses = [];

        for ($i = 0; $i < 31; $i++) {
            $statuses[] = call($app, 'POST', '/api/names', ['firstname' => "n$i"])->status;
        }

        expect(array_count_values($statuses))->toBe([201 => 30, 429 => 1]);
    });

    it('does not let route constraints be bypassed with non-numeric ids', function () {
        expect(call(testApp(), 'DELETE', '/api/names/1%20OR%201=1')->status)->toBe(404);
    });
});
