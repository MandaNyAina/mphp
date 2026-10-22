<?php

declare(strict_types=1);

namespace Mphp\Validation;

use Attribute;

/**
 * Validation rules for one field of a FormRequest: #[Rule('required|string|max:50')].
 * The same rules drive the server-side validation AND the generated TypeScript body type.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Rule
{
    /** @param string|list<string> $rules */
    public function __construct(public readonly string|array $rules) {}

    /** @return list<string> */
    public function list(): array
    {
        return is_array($this->rules) ? $this->rules : explode('|', $this->rules);
    }
}
