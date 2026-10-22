<?php

declare(strict_types=1);

use Mphp\Support\Env;

it('parses quotes, inline comments and typed values', function () {
    $file = tempDir() . '/.env';
    file_put_contents($file, "T_PLAIN=plain\nT_QUOTED=\"quoted # not a comment\"\nT_INLINE=value # a comment\n# T_SKIPPED=1\nT_BOOL=true\nT_EMPTY=\nT_SINGLE='single'\n");

    Env::load($file);

    expect(Env::get('T_PLAIN'))->toBe('plain')
        ->and(Env::get('T_QUOTED'))->toBe('quoted # not a comment')
        ->and(Env::get('T_INLINE'))->toBe('value')
        ->and(Env::get('T_SKIPPED', 'none'))->toBe('none')
        ->and(Env::get('T_BOOL'))->toBeTrue()
        ->and(Env::get('T_EMPTY'))->toBe('')
        ->and(Env::get('T_SINGLE'))->toBe('single')
        ->and(Env::get('T_MISSING', 'fallback'))->toBe('fallback');
});

it('lets real environment variables win over the file', function () {
    $file = tempDir() . '/.env';
    file_put_contents($file, "T_REAL=from-file\n");
    putenv('T_REAL=from-env');

    Env::load($file);

    expect(Env::get('T_REAL'))->toBe('from-env');
    putenv('T_REAL');
});
