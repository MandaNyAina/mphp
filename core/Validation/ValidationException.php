<?php

declare(strict_types=1);

namespace Mphp\Validation;

use Mphp\Http\HttpException;

/** Becomes a 422 JSON response: { "message": ..., "errors": { "field": ["..."] } }. */
final class ValidationException extends HttpException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(422, 'The given data was invalid.');
    }
}
