<?php

declare(strict_types=1);

namespace Mphp;

use Closure;
use LogicException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/** Tiny autowiring container: type-hint a class and it gets built for you. */
final class Container
{
    /** @var array<string, Closure(self): mixed> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = function (self $c) use ($id, $factory): object {
            return $this->instances[$id] ??= $factory($c);
        };
    }

    public function bind(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]) || class_exists($id);
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            return ($this->factories[$id])($this);
        }

        if (!class_exists($id)) {
            throw new LogicException("Nothing is bound for [$id] and it is not an instantiable class.");
        }

        return $this->build($id);
    }

    /**
     * Call a closure or [class, method] pair, resolving each parameter from
     * $named (by name or by class type), then the container, then its default value.
     *
     * @param Closure|array{0: object|string, 1: string} $callable
     * @param array<string, mixed> $named
     */
    public function call(Closure|array $callable, array $named = []): mixed
    {
        if ($callable instanceof Closure) {
            $reflection = new ReflectionFunction($callable);

            return $callable(...$this->resolveParameters($reflection->getParameters(), $named));
        }

        [$class, $method] = $callable;
        $object = is_object($class) ? $class : $this->get($class);
        $reflection = new ReflectionMethod($object, $method);

        return $reflection->invokeArgs($object, $this->resolveParameters($reflection->getParameters(), $named));
    }

    private function build(string $class): object
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        return new $class(...$this->resolveParameters($constructor->getParameters(), []));
    }

    /**
     * @param list<ReflectionParameter> $parameters
     * @param array<string, mixed> $named
     * @return array<string, mixed>
     */
    private function resolveParameters(array $parameters, array $named): array
    {
        $arguments = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                foreach ($named as $value) {
                    if ($value instanceof $typeName) {
                        $arguments[$name] = $value;
                        continue 2;
                    }
                }
                if ($this->has($typeName)) {
                    $arguments[$name] = $this->get($typeName);
                    continue;
                }
            }

            if (array_key_exists($name, $named)) {
                $arguments[$name] = $this->coerce($named[$name], $type);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[$name] = $parameter->getDefaultValue();
            } elseif ($type?->allowsNull()) {
                $arguments[$name] = null;
            } else {
                throw new LogicException("Cannot resolve parameter \${$name} of " . ($parameter->getDeclaringClass()?->getName() ?? 'closure') . '.');
            }
        }

        return $arguments;
    }

    private function coerce(mixed $value, ?\ReflectionType $type): mixed
    {
        if (!$type instanceof ReflectionNamedType || !is_string($value)) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }
}
