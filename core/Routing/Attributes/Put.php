<?php

declare(strict_types=1);

namespace Mphp\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Put extends Route
{
    /** @param list<string> $middleware */
    public function __construct(string $path = '', ?string $name = null, array $middleware = [])
    {
        parent::__construct('PUT', $path, $name, $middleware);
    }
}
