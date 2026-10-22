<?php

declare(strict_types=1);

namespace Mphp\View;

use InvalidArgumentException;
use Mphp\Http\Response;
use Mphp\Security\Csrf;

/**
 * Two ways to answer with HTML:
 *  - page('Home', $props): a Svelte page. PHP ships the props as JSON, Svelte mounts the component.
 *  - render('emails/welcome', $data): a plain PHP template from resources/views (escape with e()).
 */
final class View
{
    public function __construct(
        private readonly string $viewsPath,
        private readonly Vite $vite,
        private readonly Csrf $csrf,
        private readonly string $entry = 'resources/js/app.ts',
    ) {}

    /** @param array<string, mixed>|object $props */
    public function page(string $component, array|object $props = [], string $title = 'M-PHP', int $status = 200): Response
    {
        if (!preg_match('#^[A-Za-z0-9_/]+$#', $component)) {
            throw new InvalidArgumentException("Invalid page component name [$component].");
        }

        // HEX_* flags make the payload safe inside <script type="application/json"> (no </script> breakout).
        $json = json_encode($props, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return Response::html($this->render('shell', [
            'title' => $title,
            'component' => $component,
            'propsJson' => $json === '[]' ? '{}' : $json,
            'viteTags' => $this->vite->tags($this->entry),
            'csrfToken' => $this->csrf->token(),
        ]), $status);
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        if (!preg_match('#^[A-Za-z0-9_/.-]+$#', $template) || str_contains($template, '..')) {
            throw new InvalidArgumentException("Invalid template name [$template].");
        }

        $file = "{$this->viewsPath}/$template.php";
        if (!is_file($file)) {
            throw new InvalidArgumentException("View [$template] not found.");
        }

        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                require $__file;

                return (string) ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        })($file, $data);
    }
}
