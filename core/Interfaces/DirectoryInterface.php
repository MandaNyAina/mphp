<?php

namespace Core\Interfaces;

interface DirectoryInterface {
    public function siteUrl(): string;
    public function isDir($filename): bool;
    public function createDir(string $dir): bool;
    public function renameDir(string $old_dir, string $new_dir): bool;
    public function deleteDir(string $dir): bool;
    public function assets(string $value = null, bool $dir = false): string;
    public function controllers(string $value = null, bool $dir = false): string;
    public function models(string $value = null, bool $dir = false): string;
    public function templates(string $value = null, bool $dir = false): string;
}