<?php

declare(strict_types=1);

namespace Mphp\Routing;

use Mphp\Routing\Attributes\Prefix;
use Mphp\Routing\Attributes\Route;
use ReflectionAttribute;
use ReflectionClass;

/** Finds #[Get]/#[Post]/... attributes on controller methods and registers them on the router. */
final class RouteScanner
{
    public function __construct(private readonly string $directory, private readonly string $namespace) {}

    public function scanInto(Router $router): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS));
        $paths = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }
        sort($paths); // deterministic order => deterministic route priority

        foreach ($paths as $path) {
            $relative = substr($path, strlen(rtrim($this->directory, '/')) + 1, -4);
            $class = $this->namespace . '\\' . str_replace('/', '\\', $relative);

            if (class_exists($class)) {
                $this->scanClass(new ReflectionClass($class), $router);
            }
        }
    }

    private function scanClass(ReflectionClass $class, Router $router): void
    {
        if ($class->isAbstract() || $class->isInterface()) {
            return;
        }

        $prefix = '';
        $classMiddleware = [];
        foreach ($class->getAttributes(Prefix::class) as $attribute) {
            $instance = $attribute->newInstance();
            $prefix = '/' . trim($instance->path, '/');
            $classMiddleware = $instance->middleware;
        }

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $route = $attribute->newInstance();
                $router->add(
                    $route->method,
                    $prefix . '/' . trim($route->path, '/'),
                    [$class->getName(), $method->getName()],
                    [...$classMiddleware, ...$route->middleware],
                    $route->name,
                );
            }
        }
    }
}
