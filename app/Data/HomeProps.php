<?php

declare(strict_types=1);

namespace App\Data;

use Mphp\Typegen\Ts;

/** Props of resources/pages/Home.svelte. */
final readonly class HomeProps
{
    /** @param list<NameData> $names */
    public function __construct(
        public string $appName,
        public string $phpVersion,
        #[Ts('NameData[]')]
        public array $names,
    ) {}
}
