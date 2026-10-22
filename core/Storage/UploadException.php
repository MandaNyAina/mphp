<?php

declare(strict_types=1);

namespace Mphp\Storage;

use Mphp\Http\HttpException;

/** A rejected upload is the client's fault: 422, with a message safe to show. */
final class UploadException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(422, $message);
    }
}
