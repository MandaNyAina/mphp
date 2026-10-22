<?php

declare(strict_types=1);

namespace App\Requests;

use Mphp\Http\FormRequest;
use Mphp\Validation\Rule;

/** Body of POST /api/names and PUT /api/names/{id}: validated server-side, typed client-side. */
final readonly class NameInput extends FormRequest
{
    public function __construct(
        #[Rule('required|string|min:1|max:50')]
        public string $firstname,
    ) {}
}
