<?php

namespace Core\Lib\Plugins;
use Core\Interfaces\DirectoryInterface;
use \Core\Lib\Plugins\Directory;
use \Core\Interfaces\UploaderInterface;
use UnexpectedValueException;

class Uploader implements UploaderInterface {
    private ?DirectoryInterface $directory_manager = null;
    private array $unauthorized_types = ["", "js", "ini", "php", "jvm", "exe", "py", "c", "cpp", "ts", "sql", "psql", "json", "env", "jar", "dump"];

    public function __construct() {
        $this->directory_manager = new Directory();
    }

    private function is_authorized_file_extension(string $file_extension): bool {
        if (!in_array($file_extension, $this->unauthorized_types)) {
            return false;
        }
        return true;
    }

    private function is_unaccepted_file_size(int $file_size, int $limit_file_size): bool {
        return $file_size > $limit_file_size;
    }

    /**
     * @throws UnexpectedValueException
     */
    private function is_match_file_type(string $need_type_file, string $file_extension): void {
        switch ($need_type_file) {
            case 'image':
                $validExt = array("jpg", "jpeg", "png", "gif");
                if (!in_array($file_extension, $validExt)) { throw new \UnexpectedValueException (NOT_IMAGE_FILE); }
                break;

            case 'doc':
                $validExt = array("pdf", "doc", "docx", "odt", "xls", "xlsx");
                if (!in_array($file_extension, $validExt)) { throw new \UnexpectedValueException(NOT_DOCUMENT_FILE); }
                break;

            case 'pdf':
                $validExt = array("pdf");
                if (!in_array($file_extension, $validExt)) { throw new \UnexpectedValueException(NOT_PDF_FILE); }
                break;

            case 'audio':
                $validExt = array("mp3", "ogg", "wav");
                if (!in_array($file_extension, $validExt)) { throw new \UnexpectedValueException(NOT_AUDIO_FILE); }
                break;

            case 'video':
                $validExt = array("mp4", "avi", "mkv", "webm");
                if (!in_array($file_extension, $validExt)) { throw new \UnexpectedValueException(NOT_VIDEO_FILE); }
                break;

            default:
                throw new \UnexpectedValueException(FILE_TYPE_NOT_FOUND);
        }
    }

    /**
     * @throws UnexpectedValueException
     */
    private function upload_validator(string $file_extension, int $file_size, array $requirements): void {
        if ($this->is_authorized_file_extension($file_extension)) { throw new \UnexpectedValueException(UNAUTHORIZED_FILE); }
        $needed_file_type = $requirements['needed_file_type'] ?? null;
        $limit_size_type = $requirements['limit_size_type'] ?? 204800;
        $this->is_match_file_type($needed_file_type, $file_extension);
        if ($this->is_unaccepted_file_size($file_size, $limit_size_type)) {
            throw new \UnexpectedValueException(FILE_SIZE_TOO_LARGER);
        }
    }

    private function get_file_extension(string $filename): string {
        return strtolower(substr(strchr($filename, "."), 1));
    }

    public function set_unauthorized_types(array $custom_unauthorized_upload_file): void {
        $this->unauthorized_types = $custom_unauthorized_upload_file;
    }

    public function start(array $file, array $config, array $requirements): bool {
        $path = $config['path'];
        $filename = $config['filename'];
        if (!$this->directory_manager->isDir($path)) {
            $this->directory_manager->createDir($path);
        }

        $source_filename = $file["name"];
        $file_size = $file["size"];
        $tmp = $file["tmp_name"];

        $file_extension = $this->get_file_extension($source_filename);
        $destination_file = $path . $filename . "." . $file_extension;

        $this->upload_validator($file_extension, $file_size, $requirements);
        if (!move_uploaded_file($tmp, $destination_file)) {
            throw new \UnexpectedValueException(FILE_UPLOAD_ERROR);
        }

        return true;
    }

}