<?php

declare(strict_types=1);

namespace Mphp\Http;

final class Request
{
    /** Route parameters ({id}), filled in by the router. @var array<string, string> */
    public private(set) array $attributes = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers lower-cased names
     * @param array<string, string> $cookies
     * @param array<string, mixed> $files raw $_FILES
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly array $cookies = [],
        public readonly array $files = [],
        public readonly array $server = [],
        public readonly bool $secure = false,
        public readonly ?string $bodyError = null,
    ) {}

    public static function capture(bool $trustProxy = false): self
    {
        $server = $_SERVER;
        $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        $path = self::normalizePath((string) parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH));

        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[strtolower(str_replace('_', '-', $key))] = (string) $value;
            }
        }

        $secure = ($server['HTTPS'] ?? 'off') !== 'off' || (int) ($server['SERVER_PORT'] ?? 0) === 443;
        if ($trustProxy && strtolower($headers['x-forwarded-proto'] ?? '') === 'https') {
            $secure = true;
        }

        $raw = file_get_contents('php://input') ?: '';

        // A malformed body must not crash capture(): the kernel turns it into a 400 behind the security headers.
        try {
            $body = self::parseBody($headers, $raw, $_POST);
            $bodyError = null;
        } catch (HttpException $e) {
            [$body, $bodyError] = [[], $e->getMessage()];
        }

        return new self($method, $path, $_GET, $body, $headers, $_COOKIE, $_FILES, $server, $secure, $bodyError);
    }

    /**
     * Build a request by hand: handy for tests and CLI tools.
     *
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public static function create(string $method, string $uri, array $body = [], array $headers = []): self
    {
        $parts = parse_url($uri);
        parse_str($parts['query'] ?? '', $query);
        $headers = array_change_key_case($headers, CASE_LOWER);

        if ($body !== [] && !isset($headers['content-type'])) {
            $headers['content-type'] = 'application/json';
        }

        return new self(strtoupper($method), self::normalizePath($parts['path'] ?? '/'), $query, $body, $headers, [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function parseBody(array $headers, string $raw, array $post): array
    {
        $type = strtolower(trim(explode(';', $headers['content-type'] ?? '')[0]));

        if ($type === 'application/json') {
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new HttpException(400, 'Malformed JSON body.');
            }

            return $decoded;
        }

        if ($post !== []) {
            return $post;
        }

        // PHP only fills $_POST for POST; parse urlencoded PUT/PATCH/DELETE ourselves.
        if ($type === 'application/x-www-form-urlencoded' && $raw !== '') {
            parse_str($raw, $parsed);

            return $parsed;
        }

        return [];
    }

    private static function normalizePath(string $path): string
    {
        return '/' . trim(rawurldecode($path), '/');
    }

    /** @param array<string, string> $attributes */
    public function withAttributes(array $attributes): self
    {
        $clone = clone $this;
        $clone->attributes = $attributes;

        return $clone;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** Body first, then query string. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->body + $this->query;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        return $this->cookies[$name] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization', '');

        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower($this->header('content-type', '')), 'json');
    }

    public function wantsJson(): bool
    {
        return $this->isJson()
            || str_contains(strtolower($this->header('accept', '')), 'json')
            || str_starts_with($this->path, '/api/');
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isSafeMethod(): bool
    {
        return in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }
}
