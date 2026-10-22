<?php

declare(strict_types=1);

namespace Mphp\View;

use RuntimeException;

/**
 * Emits the <script>/<link> tags for a Vite entry.
 * - `npm run dev` writes public/hot with the dev-server URL => tags point at the dev server (HMR).
 * - otherwise tags come from public/build/.vite/manifest.json (hashed, cache-friendly files).
 */
final class Vite
{
    public function __construct(private readonly string $publicPath, private readonly string $buildDir = 'build') {}

    public function devServerUrl(): ?string
    {
        $hot = $this->publicPath . '/hot';

        return is_file($hot) ? rtrim(trim((string) file_get_contents($hot)), '/') : null;
    }

    public function tags(string $entry): string
    {
        if (($dev = $this->devServerUrl()) !== null) {
            return sprintf(
                '<script type="module" src="%s/@vite/client"></script><script type="module" src="%s/%s"></script>',
                e($dev),
                e($dev),
                e($entry),
            );
        }

        $manifest = $this->manifest();
        if (!isset($manifest[$entry])) {
            throw new RuntimeException("Vite entry [$entry] not found in the manifest. Run: npm run build");
        }

        $css = [];
        $preload = [];
        $this->collect($manifest, $entry, $css, $preload, []);

        $html = '';
        foreach (array_unique($css) as $file) {
            $html .= sprintf('<link rel="stylesheet" href="%s">', e($this->url($file)));
        }
        foreach (array_unique($preload) as $file) {
            $html .= sprintf('<link rel="modulepreload" href="%s">', e($this->url($file)));
        }

        return $html . sprintf('<script type="module" src="%s"></script>', e($this->url($manifest[$entry]['file'])));
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @param list<string> $css
     * @param list<string> $preload
     * @param list<string> $seen
     */
    private function collect(array $manifest, string $key, array &$css, array &$preload, array $seen): void
    {
        if (in_array($key, $seen, true) || !isset($manifest[$key])) {
            return;
        }
        $seen[] = $key;
        $chunk = $manifest[$key];

        array_push($css, ...($chunk['css'] ?? []));
        foreach ($chunk['imports'] ?? [] as $import) {
            if (isset($manifest[$import])) {
                $preload[] = $manifest[$import]['file'];
                $this->collect($manifest, $import, $css, $preload, $seen);
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function manifest(): array
    {
        $path = "{$this->publicPath}/{$this->buildDir}/.vite/manifest.json";

        if (!is_file($path)) {
            throw new RuntimeException('Front-end assets are not built. Run: npm install && npm run build');
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    private function url(string $file): string
    {
        return "/{$this->buildDir}/$file";
    }
}
