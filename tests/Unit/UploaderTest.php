<?php

declare(strict_types=1);

use Mphp\Storage\UploadException;
use Mphp\Storage\Uploader;

const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function fakeUpload(string $content, string $clientName): array
{
    $tmp = tempDir() . '/upload.tmp';
    file_put_contents($tmp, $content);

    return ['name' => $clientName, 'tmp_name' => $tmp, 'size' => strlen($content), 'error' => UPLOAD_ERR_OK];
}

// move_uploaded_file() refuses non-HTTP uploads, so tests inject a plain copy.
$uploader = fn(string $dir) => new Uploader($dir, fn(string $from, string $to): bool => copy($from, $to));

it('stores a real image under a random name with the sniffed extension', function () use ($uploader) {
    $dir = tempDir();

    $name = $uploader($dir)->store(fakeUpload(base64_decode(TINY_PNG), 'holiday.jpg'), 'image');

    expect($name)->toMatch('/^[a-f0-9]{32}\.png$/')->and(is_file("$dir/$name"))->toBeTrue();
});

it('rejects a PHP script disguised as an image', function () use ($uploader) {
    $dir = tempDir();

    expect(fn() => $uploader($dir)->store(fakeUpload('<?php system($_GET["c"]);', 'avatar.png'), 'image'))
        ->toThrow(UploadException::class, 'not allowed')
        ->and(glob("$dir/*"))->toBe([]);
});

it('rejects oversized files, failed uploads and unknown groups', function () use ($uploader) {
    $png = base64_decode(TINY_PNG);

    expect(fn() => $uploader(tempDir())->store(fakeUpload($png, 'a.png'), 'image', maxBytes: 10))->toThrow(UploadException::class, 'too large')
        ->and(fn() => $uploader(tempDir())->store(['error' => UPLOAD_ERR_INI_SIZE], 'image'))->toThrow(UploadException::class)
        ->and(fn() => $uploader(tempDir())->store(fakeUpload($png, 'a.png'), 'exe'))->toThrow(InvalidArgumentException::class);
});
