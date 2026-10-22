<?php

declare(strict_types=1);

namespace Mphp\Storage;

use Closure;
use RuntimeException;

/**
 * Safe uploads: the type is sniffed from the file CONTENT (finfo), never from the client-supplied
 * name or MIME; the stored name is random and its extension comes from the sniffed type.
 */
final class Uploader
{
    /** @var array<string, array<string, string>> group => [mime => extension] */
    public const GROUPS = [
        'image' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'],
        'pdf' => ['application/pdf' => 'pdf'],
        'audio' => ['audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav'],
        'video' => ['video/mp4' => 'mp4', 'video/webm' => 'webm'],
    ];

    /** @param (Closure(string, string): bool)|null $mover defaults to move_uploaded_file; override in tests */
    public function __construct(private readonly string $directory, private readonly ?Closure $mover = null) {}

    /**
     * @param array{name?: string, tmp_name?: string, size?: int, error?: int} $file one entry of $_FILES
     * @param string $group a key of self::GROUPS
     * @return string stored file name (relative to the upload directory)
     * @throws UploadException
     */
    public function store(array $file, string $group = 'image', int $maxBytes = 2_097_152): string
    {
        $allowed = self::GROUPS[$group] ?? throw new \InvalidArgumentException("Unknown upload group [$group].");

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new UploadException('Upload failed (code ' . ($file['error'] ?? 'none') . ').');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new UploadException('No uploaded file received.');
        }
        if (filesize($tmp) > $maxBytes) {
            throw new UploadException('File is too large.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !isset($allowed[$mime])) {
            throw new UploadException("File type not allowed for [$group].");
        }

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Cannot create the upload directory.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $move = $this->mover ?? move_uploaded_file(...);

        if (!$move($tmp, rtrim($this->directory, '/') . '/' . $name)) {
            throw new UploadException('Could not store the uploaded file.');
        }

        return $name;
    }
}
