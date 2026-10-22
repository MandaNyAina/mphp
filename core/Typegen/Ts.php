<?php

declare(strict_types=1);

namespace Mphp\Typegen;

use Attribute;

/**
 * Override the generated TypeScript type:
 *  - on a DTO property: #[Ts('NameData[]')] public array $names
 *  - on a controller action: the type of the JSON the route answers with, when the PHP return type
 *    cannot say it (array, Response): #[Ts('NameData[]')] / #[Ts('void')]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::TARGET_METHOD)]
final class Ts
{
    public function __construct(public readonly string $type) {}
}
