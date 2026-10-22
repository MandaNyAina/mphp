<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Name;

/**
 * What the API sends to the browser. `php bin/mphp types:generate` turns this class
 * into `interface NameData` in resources/js/generated/types.ts: PHP stays the source of truth.
 */
final readonly class NameData
{
    public function __construct(
        public int $id,
        public string $firstname,
        public string $createdAt,
    ) {}

    public static function fromModel(Name $name): self
    {
        return new self((int) $name->id, (string) $name->firstname, (string) $name->created_at);
    }
}
