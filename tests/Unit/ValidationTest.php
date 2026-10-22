<?php

declare(strict_types=1);

use Mphp\Validation\ValidationException;
use Mphp\Validation\Validator;

function validationErrors(array $data, array $rules): array
{
    try {
        Validator::validate($data, $rules);
    } catch (ValidationException $e) {
        return $e->errors;
    }

    return [];
}

it('returns only declared fields, cast to their type', function () {
    $clean = Validator::validate(
        ['name' => 'Ada', 'age' => '36', 'admin' => true, 'active' => 'true', 'ratio' => '1.5'],
        ['name' => 'required|string|max:50', 'age' => 'required|integer|min:0', 'active' => 'boolean', 'ratio' => 'numeric'],
    );

    expect($clean)->toBe(['name' => 'Ada', 'age' => 36, 'active' => true, 'ratio' => 1.5]);
});

it('collects every failing field with readable messages', function () {
    $errors = validationErrors(
        ['name' => str_repeat('x', 51), 'email' => 'nope', 'age' => 'abc'],
        ['name' => 'required|max:50', 'email' => 'required|email', 'age' => 'integer', 'role' => 'required'],
    );

    expect($errors)->toHaveKeys(['name', 'email', 'age', 'role'])
        ->and($errors['name'][0])->toBe('The name field must not be greater than 50.')
        ->and($errors['role'][0])->toBe('The role field is required.');
});

it('treats nullable and optional fields as skippable', function () {
    expect(Validator::validate(['bio' => ''], ['bio' => 'nullable|string|max:10', 'nick' => 'string']))->toBe(['bio' => null]);
});

it('checks in:, regex: and min/max semantics for strings, numbers and arrays', function () {
    expect(validationErrors(['c' => 'red'], ['c' => 'in:red,green']))->toBe([])
        ->and(validationErrors(['c' => 'blue'], ['c' => 'in:red,green']))->toHaveKey('c')
        ->and(validationErrors(['z' => 'AB12'], ['z' => ['regex:/^[A-Z]{2}\d{2}$/']]))->toBe([])
        ->and(validationErrors(['n' => '5'], ['n' => 'integer|min:10']))->toHaveKey('n')
        ->and(validationErrors(['t' => ['a']], ['t' => 'array|min:2']))->toHaveKey('t');
});

it('builds a typed FormRequest from the request, dropping unknown fields', function () {
    $input = App\Requests\NameInput::from(Mphp\Http\Request::create('POST', '/x', ['firstname' => 'Ada', 'admin' => true]));

    expect($input->firstname)->toBe('Ada')->and($input->toArray())->toBe(['firstname' => 'Ada']);
});

it('refuses an invalid FormRequest with a 422', function () {
    expect(fn() => App\Requests\NameInput::from(Mphp\Http\Request::create('POST', '/x', ['firstname' => str_repeat('x', 51)])))
        ->toThrow(ValidationException::class);
});

it('exposes the rules of a FormRequest per field', function () {
    expect(App\Requests\NameInput::fields())->toBe(['firstname' => ['required', 'string', 'min:1', 'max:50']]);
});

it('fails loudly on a typo in a rule name', function () {
    expect(fn() => Validator::validate(['a' => 'x'], ['a' => 'requird']))->toThrow(InvalidArgumentException::class, 'requird');
});
