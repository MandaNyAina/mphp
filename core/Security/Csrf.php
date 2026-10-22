<?php

declare(strict_types=1);

namespace Mphp\Security;

/** Synchronizer-token CSRF protection backed by the session. */
final class Csrf
{
    public function __construct(private readonly Session $session) {}

    public function token(): string
    {
        $token = $this->session->get('_csrf');

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->put('_csrf', $token);
        }

        return $token;
    }

    public function verify(?string $given): bool
    {
        return $given !== null && $given !== '' && hash_equals($this->token(), $given);
    }
}
