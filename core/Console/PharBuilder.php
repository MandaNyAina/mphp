<?php

declare(strict_types=1);

namespace Mphp\Console;

use Phar;
use RuntimeException;

/**
 * Packs the application into ONE file: `php mphp.phar serve` is all a server needs.
 * Code and built assets live inside the archive (read-only); `.env` and `storage/` stay
 * next to it, in the directory you run it from (or MPHP_HOME).
 */
final class PharBuilder
{
    private const DIRECTORIES = ['app', 'config', 'core', 'database/migrations', 'public', 'resources/views'];

    private const FILES = ['.env.example', 'storage/cache/routes.php'];

    /** @return int number of files in the archive */
    public static function build(string $basePath, string $target): int
    {
        if (ini_get('phar.readonly')) {
            throw new RuntimeException('phar.readonly is on. Run: php -d phar.readonly=0 bin/mphp compile');
        }

        @mkdir(dirname($target), 0775, true);
        @unlink($target);

        $phar = new Phar($target, 0, 'mphp.phar');
        $phar->startBuffering();

        foreach (self::DIRECTORIES as $directory) {
            $root = $basePath . '/' . $directory;
            if (!is_dir($root)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $relative = $directory . substr($file->getPathname(), strlen($root));
                if ($file->isFile() && $relative !== 'public/hot') {
                    $phar->addFile($file->getPathname(), $relative);
                }
            }
        }
        foreach (self::FILES as $file) {
            if (is_file($basePath . '/' . $file)) {
                $phar->addFile($basePath . '/' . $file, $file);
            }
        }

        $phar->setStub((string) file_get_contents(__DIR__ . '/phar-stub.php'));
        $phar->stopBuffering();
        $count = $phar->count();
        unset($phar);

        chmod($target, 0755);

        return $count;
    }
}
