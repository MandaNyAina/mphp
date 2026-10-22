<?php

declare(strict_types=1);

use Mphp\Http\Request;
use Mphp\Http\Response;
use Mphp\Middleware\Throttle;
use Mphp\Middleware\VerifyCsrf;
use Mphp\Security\Crypto;
use Mphp\Security\Csrf;
use Mphp\Security\Passwords;
use Mphp\Security\Session;

$ok = static fn(Request $r): Response => Response::text('ok');

describe('Crypto', function () {
    it('round-trips and uses a fresh nonce each time', function () {
        $crypto = new Crypto(Crypto::generateKey());

        $a = $crypto->encrypt('secret');
        $b = $crypto->encrypt('secret');

        expect($crypto->decrypt($a))->toBe('secret')->and($a)->not->toBe($b);
    });

    it('returns null for tampered payloads and for a different key', function () {
        $crypto = new Crypto(Crypto::generateKey());
        $payload = $crypto->encrypt('secret');
        $tampered = substr($payload, 0, -2) . (str_ends_with($payload, 'AA') ? 'BB' : 'AA');

        expect($crypto->decrypt($tampered))->toBeNull()
            ->and($crypto->decrypt('garbage'))->toBeNull()
            ->and((new Crypto(Crypto::generateKey()))->decrypt($payload))->toBeNull();
    });

    it('refuses to start without a valid key', function () {
        expect(fn() => new Crypto(''))->toThrow(RuntimeException::class, 'key:generate');
    });

    it('signs and verifies, and generates random strings of the right length', function () {
        $crypto = new Crypto(Crypto::generateKey());

        expect($crypto->verifySignature('data', $crypto->sign('data')))->toBeTrue()
            ->and($crypto->verifySignature('other', $crypto->sign('data')))->toBeFalse()
            ->and(Crypto::randomString(40))->toMatch('/^[A-Za-z0-9]{40}$/');
    });
});

describe('Passwords', function () {
    it('hashes, verifies and judges strength by length', function () {
        $hash = Passwords::hash('correct horse battery');

        expect(Passwords::verify('correct horse battery', $hash))->toBeTrue()
            ->and(Passwords::verify('wrong', $hash))->toBeFalse()
            ->and(Passwords::needsRehash($hash))->toBeFalse()
            ->and(Passwords::isStrong('short'))->toBeFalse()
            ->and(Passwords::isStrong('long enough passphrase'))->toBeTrue();
    });
});

describe('CSRF middleware', function () use ($ok) {
    it('blocks unsafe requests without a token and lets safe ones through', function () use ($ok) {
        $csrf = new Csrf(new Session());
        $middleware = new VerifyCsrf($csrf);

        expect(httpStatus(fn() => $middleware->handle(Request::create('POST', '/x'), $ok))[0])->toBe(419)
            ->and(httpStatus(fn() => $middleware->handle(Request::create('POST', '/x', headers: ['X-CSRF-Token' => 'wrong']), $ok))[0])->toBe(419)
            ->and($middleware->handle(Request::create('GET', '/x'), $ok)->status)->toBe(200)
            ->and($middleware->handle(Request::create('POST', '/x', headers: ['X-CSRF-Token' => $csrf->token()]), $ok)->status)->toBe(200);
    });

    it('accepts the token as a _token field and honours exemptions', function () use ($ok) {
        $csrf = new Csrf(new Session());
        $middleware = new VerifyCsrf($csrf, ['/webhooks/*']);

        expect($middleware->handle(Request::create('POST', '/x', ['_token' => $csrf->token()]), $ok)->status)->toBe(200)
            ->and($middleware->handle(Request::create('POST', '/webhooks/stripe'), $ok)->status)->toBe(200)
            ->and($middleware->handle(Request::create('POST', '/x', headers: ['Authorization' => 'Bearer t']), $ok)->status)->toBe(200);
    });
});

describe('Throttle middleware', function () use ($ok) {
    it('allows the limit then answers 429 with Retry-After', function () use ($ok) {
        $throttle = new Throttle(tempDir());
        $request = Request::create('GET', '/x');

        $first = $throttle->handle($request, $ok, '2', '60');
        $throttle->handle($request, $ok, '2', '60');
        [$status, $headers] = httpStatus(fn() => $throttle->handle($request, $ok, '2', '60'));

        expect($first->header('X-RateLimit-Remaining'))->toBe('1')
            ->and($status)->toBe(429)
            ->and((int) $headers['Retry-After'])->toBeGreaterThan(0);
    });
});
