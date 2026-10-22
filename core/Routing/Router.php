<?php

declare(strict_types=1);

namespace Mphp\Routing;

use Closure;
use Mphp\Http\HttpException;

final class Router
{
    /** @var list<RouteDefinition> */
    private array $routes = [];

    /** @var array<string, RouteDefinition> "METHOD /path" => route, for routes without parameters */
    private array $static = [];

    /** @var array<string, RouteDefinition> */
    private array $named = [];

    /**
     * @param Closure|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function add(string $method, string $path, Closure|array $handler, array $middleware = [], ?string $name = null): RouteDefinition
    {
        $path = '/' . trim($path, '/');
        $route = new RouteDefinition(strtoupper($method), $path, $handler, $middleware, $name);

        $this->routes[] = $route;
        if ($route->isStatic()) {
            $this->static[$route->method . ' ' . $path] ??= $route;
        }
        if ($name !== null) {
            $this->named[$name] = $route;
        }

        return $route;
    }

    /** @param Closure|array{0: class-string, 1: string} $handler @param list<string> $middleware */
    public function get(string $path, Closure|array $handler, array $middleware = [], ?string $name = null): RouteDefinition
    {
        return $this->add('GET', $path, $handler, $middleware, $name);
    }

    /** @param Closure|array{0: class-string, 1: string} $handler @param list<string> $middleware */
    public function post(string $path, Closure|array $handler, array $middleware = [], ?string $name = null): RouteDefinition
    {
        return $this->add('POST', $path, $handler, $middleware, $name);
    }

    /** @param Closure|array{0: class-string, 1: string} $handler @param list<string> $middleware */
    public function put(string $path, Closure|array $handler, array $middleware = [], ?string $name = null): RouteDefinition
    {
        return $this->add('PUT', $path, $handler, $middleware, $name);
    }

    /** @param Closure|array{0: class-string, 1: string} $handler @param list<string> $middleware */
    public function delete(string $path, Closure|array $handler, array $middleware = [], ?string $name = null): RouteDefinition
    {
        return $this->add('DELETE', $path, $handler, $middleware, $name);
    }

    /**
     * @return array{0: RouteDefinition, 1: array<string, string>}
     * @throws HttpException 404 when nothing matches the path, 405 when only other methods do
     */
    public function match(string $method, string $path): array
    {
        $method = strtoupper($method);
        $lookupMethod = $method === 'HEAD' ? 'GET' : $method;

        if (isset($this->static["$lookupMethod $path"])) {
            return [$this->static["$lookupMethod $path"], []];
        }

        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route->regex, $path, $matches)) {
                continue;
            }
            if ($route->method !== $lookupMethod) {
                $allowed[$route->method] = true;
                continue;
            }

            // The path was already decoded once by Request: decoding again would turn %2520 into a space.
            return [$route, array_intersect_key($matches, array_flip($route->paramNames))];
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Method not allowed.', ['Allow' => implode(', ', array_keys($allowed))]);
        }

        throw new HttpException(404, 'Not found.');
    }

    /** @param array<string, scalar> $params */
    public function url(string $name, array $params = []): string
    {
        return ($this->named[$name] ?? throw new \InvalidArgumentException("Unknown route [$name]."))->url($params);
    }

    /** @return list<RouteDefinition> */
    public function routes(): array
    {
        return $this->routes;
    }

    /** Closures cannot be serialised: the cache only works when every handler is a [class, method]. */
    public function isCacheable(): bool
    {
        return array_all($this->routes, static fn(RouteDefinition $r): bool => is_array($r->handler));
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: string, 4: list<string>, 5: ?string}> */
    public function export(): array
    {
        return array_map(
            static fn(RouteDefinition $r): array => [$r->method, $r->path, $r->handler[0], $r->handler[1], $r->middleware, $r->name],
            $this->routes,
        );
    }

    /** @param list<array{0: string, 1: string, 2: class-string, 3: string, 4: list<string>, 5: ?string}> $exported */
    public function import(array $exported): void
    {
        foreach ($exported as [$method, $path, $class, $action, $middleware, $name]) {
            $this->add($method, $path, [$class, $action], $middleware, $name);
        }
    }
}
