<?php

declare(strict_types=1);

namespace Mphp\Routing;

use Closure;

final readonly class RouteDefinition
{
    /** @var list<string> */
    public array $paramNames;

    public string $regex;

    /**
     * @param Closure|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function __construct(
        public string $method,
        public string $path,
        public Closure|array $handler,
        public array $middleware = [],
        public ?string $name = null,
    ) {
        $names = [];
        $regex = '';

        // Split "/users/{id:\d+}/posts" into literal and {param} pieces.
        foreach (preg_split('/(\{[A-Za-z_][A-Za-z0-9_]*(?::[^}]+)?\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE) as $piece) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(?::([^}]+))?\}$/', $piece, $m)) {
                $names[] = $m[1];
                $regex .= '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
            } else {
                $regex .= preg_quote($piece, '#');
            }
        }

        $this->paramNames = $names;
        $this->regex = '#^' . $regex . '$#';
    }

    public function isStatic(): bool
    {
        return $this->paramNames === [];
    }

    /** @param array<string, scalar> $params */
    public function url(array $params = []): string
    {
        return (string) preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)(?::[^}]+)?\}/',
            static fn(array $m): string => rawurlencode((string) ($params[$m[1]] ?? throw new \InvalidArgumentException("Missing route parameter [{$m[1]}]."))),
            $this->path,
        );
    }
}
