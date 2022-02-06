<?php

namespace Core\Lib\Main;
use \Core\Lib\Main\MainViews;
use \Core\Interfaces\CoreInterface\MphpCoreInterface;
use DirectoryIterator;

class MphpCore implements MphpCoreInterface {

    public static function secure_url() {
        $uri = explode("/", $_SERVER['REQUEST_URI']);
        $uri_invalid = str_exist("js", end($uri)) || str_exist("css", end($uri)) || str_exist("json", end($uri)) || str_exist("env", end($uri)) || str_exist("ini", end($uri));
        if ($uri_invalid) {
            self::show_error_server(403);
        }
    }

    private static function get_directory_in_folder(string $directory_path): array {
        $directories_iterator = new DirectoryIterator($directory_path);
        $directories = [];
        foreach ($directories_iterator as $file_info) {
            if ($file_info->isDir() && !$file_info->isDot()) {
                $directories[] = $file_info->getFilename();
            }
        }
        return $directories;
    }

    private static function get_result_path(string $path, ?string $result_path, string $file): string {
        $directories = self::get_directory_in_folder($path);
        if (count($directories)) {
            if ($result_path === null) {
                $result_path = self::find_file_on_path($path, $directories, $file);
            }
        } else {
            if (file_exists($path.$file)) {
                return $path.$file;
            }
        }
        return $result_path;
    }

    private static function find_file_on_path(string $main_path, array $directory_list, string $file): ?string {
        $path = $main_path;
        $result_path = null;
        $directories_list_already_checked = [];
        foreach ($directory_list as $directory) {
            if (!in_array($path, $directories_list_already_checked) && is_dir($path)) {
                $path = str_replace("//", "/", $main_path.'/'.$directory.'/');
                if (is_dir($path)) {
                    $result_path = self::get_result_path($path, $result_path, $file);
                    if ($result_path !== null) {
                        break;
                    }
                }
            }
        }
        return $result_path;
    }

    public static function get_file_in_folder(string $directory_path, string $filename): ?string {
        if (file_exists($directory_path.$filename)) {
            return $directory_path.$filename;
        } else {
            $init_folder = self::get_directory_in_folder($directory_path);
            return self::find_file_on_path($directory_path, $init_folder, $filename);
        }
    }

    public static function get_namespace_by_php_file(string $base_path, string $file): string {
        $base_path_array = explode('/', $base_path);
        $file_array = explode('/', $file);
        $array_diff = [];
        foreach ($file_array as $each_file_properties) {
            if (!in_array($each_file_properties, $base_path_array)) {
                $array_diff[] = $each_file_properties;
            }
        }
        array_pop($array_diff);
        return count($array_diff) ? implode('\\', $array_diff).'\\' : '';
    }

    public static function call_dynamic_class(string $class_name, string $class_file_path) {
        if (file_exists($class_file_path)) {
            if (!class_exists($class_name)) {
                require $class_file_path;
            }
            return new $class_name();
        } else {
            self::show_error_server(500, " Class $class_name undefined");
        }
        return null;
    }

    public static function show_error_server(int $error_code, ?string $info = null): void {
        $views_manager = new MainViews();
        switch ($error_code) {
            case 404:
                $views_manager->load_views('404/notfound', ['page_name' => 'Not found']);
                exit();

            case 403:
                $views_manager->load_views('403/forbidden', ['page_name' => 'Not authorized']);
                exit();

            case 500:
                if (CONFIG['mode_env'] == 'production') {
                    $views_manager->load_views('500/errorserver', ['page_name' => 'Error server side']);
                } else if (CONFIG['mode_env'] == 'development') {
                    echo '
                        <div class="alert alert-danger w-100">
                            <h3 class="text-dark text-center">APP ERROR <i class="fa fa-bug" aria-hidden="true"></i></h3>
                            <b>Raison : </b>' . $info . '<br/><br/>
                            <b>Fichier : </b>' . $_SERVER['PHP_SELF'] . '
                        </div>
                        ';
                }
                exit();

            default:
                exit();
        }
    }

}