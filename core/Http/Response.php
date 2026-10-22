<?php

declare(strict_types=1);

namespace Mphp\Http;

use InvalidArgumentException;

final class Response
{
    /** @var array<string, string> canonical-cased header names */
    private array $headers = [];

    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[self::canonical($name)] = self::clean($value);
        }
    }

    /** @param array<string, string> $headers */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;

        return new self(json_encode($data, $flags), $status, ['Content-Type' => 'application/json; charset=utf-8'] + $headers);
    }

    /** @param array<string, string> $headers */
    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8'] + $headers);
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($text, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[self::canonical($name)] = self::clean($value);

        return $clone;
    }

    public function withHeaderIfMissing(string $name, string $value): self
    {
        return $this->hasHeader($name) ? $this : $this->withHeader($name, $value);
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[self::canonical($name)]);
    }

    public function header(string $name): ?string
    {
        return $this->headers[self::canonical($name)] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->status);
        header_remove('X-Powered-By');

        foreach ($this->headers as $name => $value) {
            header("$name: $value", true);
        }

        echo $this->body;
    }

    private static function canonical(string $name): string
    {
        return ucwords(strtolower($name), '-');
    }

    /** Header values must never carry CR/LF (response splitting). */
    private static function clean(string $value): string
    {
        if (preg_match('/[\r\n]/', $value)) {
            throw new InvalidArgumentException('Header values must not contain line breaks.');
        }

        return $value;
    }
}
