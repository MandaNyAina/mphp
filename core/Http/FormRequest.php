<?php

declare(strict_types=1);

namespace Mphp\Http;

use Mphp\Validation\Rule;
use Mphp\Validation\Validator;
use ReflectionClass;

/**
 * A typed, validated request body. Declare it, type-hint it in an action, done:
 *
 *   final readonly class NameInput extends FormRequest {
 *       public function __construct(#[Rule('required|string|max:50')] public string $firstname) {}
 *   }
 *   public function store(NameInput $input) { ... $input->firstname ... }
 *
 * Invalid input answers 422 before the action runs; `types:generate` turns the class
 * into the TypeScript body type of the route, so the front end is checked at compile time.
 */
abstract readonly class FormRequest
{
    public static function from(Request $request): static
    {
        $rules = [];
        foreach (static::fields() as $name => $parameterRules) {
            $rules[$name] = $parameterRules;
        }

        return new static(...Validator::validate($request->all(), $rules));
    }

    /** @return array<string, list<string>> field => rules */
    public static function fields(): array
    {
        $fields = [];

        foreach ((new ReflectionClass(static::class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $attribute = $parameter->getAttributes(Rule::class)[0] ?? null;
            $fields[$parameter->getName()] = $attribute?->newInstance()->list() ?? [];
        }

        return $fields;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
