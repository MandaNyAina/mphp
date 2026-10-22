<?php

declare(strict_types=1);

namespace Mphp\Http;

use Mphp\Support\Log;
use Mphp\Validation\ValidationException;
use Mphp\View\View;
use Throwable;

/** Turns any exception into a Response: JSON for API callers, an HTML page otherwise. */
final class ErrorRenderer
{
    public function __construct(private readonly View $view, private readonly bool $debug) {}

    public function render(Request $request, Throwable $e): Response
    {
        $isHttp = $e instanceof HttpException;
        $status = $isHttp ? $e->status : 500;

        if (!$isHttp) {
            Log::error($e::class . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine(), 'path' => $request->path]);
        }

        // HttpException messages are written for clients; anything else may leak internals.
        $message = ($isHttp || $this->debug) ? $e->getMessage() : 'Server error.';
        $headers = $isHttp ? $e->headers : [];

        if ($request->wantsJson()) {
            $payload = ['message' => $message, 'status' => $status];
            if ($e instanceof ValidationException) {
                $payload['errors'] = $e->errors;
            }
            if (!$isHttp && $this->debug) {
                $payload['debug'] = ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => explode("\n", $e->getTraceAsString())];
            }

            return Response::json($payload, $status, $headers);
        }

        try {
            $template = !$isHttp && $this->debug ? 'errors/debug' : 'errors/error';

            return Response::html($this->view->render($template, ['status' => $status, 'message' => $message, 'exception' => $e]), $status, $headers);
        } catch (Throwable) {
            return new Response("Error $status", $status, ['Content-Type' => 'text/plain; charset=utf-8'] + $headers);
        }
    }
}
