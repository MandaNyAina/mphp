<?php

namespace Core\Interfaces\CoreInterface;

interface MphpCoreInterface {
    public static function get_file_in_folder(string $directory_path, string $filename): ?string;
    public static function get_namespace_by_php_file(string $base_path, string $file): string;
    public static function call_dynamic_class(string $class_name, string $class_file_path);
    public static function show_error_server(int $error_code, ?string $info = null): void;
}