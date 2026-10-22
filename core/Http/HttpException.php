<?php

declare(strict_types=1);

namespace Mphp\Http;

use RuntimeException;

/** Throw it anywhere to stop the request with a given status: abort(404) style. */
class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }
}
