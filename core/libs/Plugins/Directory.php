<?php

namespace Core\Lib\Plugins;
use Core\Interfaces\DirectoryInterface;
use Exception;
use Core\Lib\Main\MphpCore as MPHP;

class Directory implements DirectoryInterface {

    private static string $racine = ROOT_PATH."/";

    public function isDir($filename): bool {
        return is_dir($filename);
    }

    public function siteUrl(): string {
        return CONFIG['site_url'];
    }

    public function localDir(): string {
        return self::$racine;
    }

    public function createDir(string $dir): bool {
        try {
            mkdir($dir, 0775);
            return true;
        } catch (Exception $error) {
            MPHP::show_error_server(500, $error);
        }
        return false;
    }

    public function renameDir(string $old_dir, string $new_dir): bool {
        try {
            if (is_dir($old_dir)) {
                rename($old_dir, $new_dir);
                return true;
            }
            return false;
        } catch (Exception $error) {
            MPHP::show_error_server(500, $error);
        }
        return false;
    }

    public function deleteDir(string $dir): bool {
        try {
            rmdir($dir);
            return true;
        } catch (Exception $error) {
            MPHP::show_error_server(500, $error);
        }
        return false;
    }

    public function assets(string $value = null, bool $dir = false): string {
        return ($dir ? $this->localDir() : $this->siteUrl()) . 'assets/' . $value;
    }

    public function controllers(string $value = null, bool $dir = false): string {
        return ($dir ? $this->localDir() : $this->siteUrl()) . 'src/Controllers/' . $value;
    }

    public function models(string $value = null, bool $dir = false): string {
        return ($dir ? $this->localDir() : $this->siteUrl()) . 'src/Models/' . $value;
    }

    public function templates(string $value = null, bool $dir = false): string {
        return ($dir ? $this->localDir() : $this->siteUrl()) . 'src/Templates/' . $value;
    }
}
