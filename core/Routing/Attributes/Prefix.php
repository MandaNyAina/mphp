<?php

declare(strict_types=1);

namespace Mphp\Routing\Attributes;

use Attribute;

/** Class-level: every route of the controller starts with this path and inherits these middleware. */
#[Attribute(Attribute::TARGET_CLASS)]
final class Prefix
{
    /** @param list<string> $middleware */
    public function __construct(
        public readonly string $path,
        public readonly array $middleware = [],
    ) {}
}
