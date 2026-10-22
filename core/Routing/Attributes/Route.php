<?php

declare(strict_types=1);

namespace Mphp\Routing\Attributes;

use Attribute;

/**
 * Generic route attribute. Prefer the shortcuts: #[Get('/x')], #[Post('/x')]...
 *
 * `name` feeds the generated TypeScript route helper (route('names.update', { id })).
 * `middleware` entries are aliases ("csrf", "throttle:5,60") or Middleware class names.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    /** @param list<string> $middleware */
    public function __construct(
        public readonly string $method,
        public readonly string $path = '',
        public readonly ?string $name = null,
        public readonly array $middleware = [],
    ) {}
}
