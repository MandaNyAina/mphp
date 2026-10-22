<?php

declare(strict_types=1);

use Mphp\Security\Csrf;
use Mphp\Security\Session;
use Mphp\View\View;
use Mphp\View\Vite;

function viteWithManifest(array $manifest): Vite
{
    $public = tempDir();
    mkdir($public . '/build/.vite', 0777, true);
    file_put_contents($public . '/build/.vite/manifest.json', json_encode($manifest));

    return new Vite($public);
}

describe('Vite', function () {
    it('emits css, modulepreload and the entry script from the manifest', function () {
        $vite = viteWithManifest([
            'resources/js/app.ts' => ['file' => 'assets/app-1.js', 'css' => ['assets/app-1.css'], 'imports' => ['_shared.js']],
            '_shared.js' => ['file' => 'assets/shared-2.js', 'css' => ['assets/shared-2.css']],
        ]);

        $tags = $vite->tags('resources/js/app.ts');

        expect($tags)->toContain('<link rel="stylesheet" href="/build/assets/app-1.css">')
            ->and($tags)->toContain('<link rel="stylesheet" href="/build/assets/shared-2.css">')
            ->and($tags)->toContain('<link rel="modulepreload" href="/build/assets/shared-2.js">')
            ->and($tags)->toContain('<script type="module" src="/build/assets/app-1.js"></script>');
    });

    it('points at the dev server while public/hot exists', function () {
        $public = tempDir();
        file_put_contents($public . '/hot', "http://127.0.0.1:5173/\n");
        $vite = new Vite($public);

        expect($vite->devServerUrl())->toBe('http://127.0.0.1:5173')
            ->and($vite->tags('resources/js/app.ts'))->toContain('http://127.0.0.1:5173/@vite/client');
    });

    it('explains how to fix a missing build', function () {
        expect(fn() => (new Vite(tempDir()))->tags('resources/js/app.ts'))->toThrow(RuntimeException::class, 'npm install && npm run build');
    });
});

describe('View', function () {
    $view = fn() => new View(dirname(__DIR__, 2) . '/resources/views', viteWithManifest(['resources/js/app.ts' => ['file' => 'a.js']]), new Csrf(new Session()));

    it('renders a Svelte page shell with escaped props and a CSRF meta tag', function () use ($view) {
        $html = $view()->page('Home', ['x' => '</script><script>alert(1)</script>', 'q' => "'\""], title: '<b>T</b>')->body;

        expect($html)->not->toContain('</script><script>')
            ->and($html)->toContain('</script>')
            ->and($html)->toContain('data-component="Home"')
            ->and($html)->toContain('<title>&lt;b&gt;T&lt;/b&gt;</title>')
            ->and($html)->toMatch('/name="csrf-token" content="[a-f0-9]{64}"/');
    });

    it('rejects component and template names that could escape the folder', function () use ($view) {
        expect(fn() => $view()->page('../../etc/passwd'))->toThrow(InvalidArgumentException::class)
            ->and(fn() => $view()->render('../config/app'))->toThrow(InvalidArgumentException::class)
            ->and(fn() => $view()->render('missing'))->toThrow(InvalidArgumentException::class);
    });
});
