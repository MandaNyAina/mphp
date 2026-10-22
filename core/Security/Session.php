<?php

declare(strict_types=1);

namespace Mphp\Security;

/**
 * Lazy, hardened session wrapper: nothing starts until you touch it.
 * Cookies are HttpOnly + SameSite=Lax (+ Secure over HTTPS) and PHP's strict mode is on.
 * In CLI (tests, console) it falls back to an in-memory array.
 */
final class Session
{
    private bool $started = false;

    /** @var array<string, mixed> */
    private array $memory = [];

    /** @param array{name?: string, lifetime?: int, secure?: bool, samesite?: string} $options */
    public function __construct(private readonly array $options = []) {}

    public function start(): void
    {
        if ($this->started) {
            return;
        }
        $this->started = true;

        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($this->options['name'] ?? 'mphp_session');
        session_set_cookie_params([
            'lifetime' => $this->options['lifetime'] ?? 0,
            'path' => '/',
            'secure' => $this->options['secure'] ?? false,
            'httponly' => true,
            'samesite' => $this->options['samesite'] ?? 'Lax',
        ]);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $this->read()[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $this->read());
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();

        if (PHP_SAPI === 'cli') {
            $this->memory[$key] = $value;
        } else {
            $_SESSION[$key] = $value;
        }
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function forget(string $key): void
    {
        $this->start();

        if (PHP_SAPI === 'cli') {
            unset($this->memory[$key]);
        } else {
            unset($_SESSION[$key]);
        }
    }

    public function flush(): void
    {
        $this->start();

        if (PHP_SAPI === 'cli') {
            $this->memory = [];
        } else {
            $_SESSION = [];
        }
    }

    /** Call after login/privilege change to defeat session fixation. */
    public function regenerate(): void
    {
        $this->start();

        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true);
        }
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        return PHP_SAPI === 'cli' ? $this->memory : $_SESSION;
    }
}
