<?php

namespace Core\Lib\Main;
use \Core\Interfaces\CoreInterface\ViewInterface;
use Core\Lib\Main\MphpCore as MPHP;

class MainViews implements ViewInterface {

    private function get_views_file(string $views_filename): string {
        $directory_manager = new \Core\Lib\Plugins\Directory();
        $view_file = $directory_manager->templates("$views_filename.php", true);
        if (!file_exists($view_file)) {
            $view_file = $directory_manager->templates("$views_filename.html", true);
            if (file_exists($view_file)) {
                return $view_file;
            }
            return 'View undefined';
        }
        return $view_file;
    }

    public function load_views(string $view_name, array $data = []): void {
        try {
            $render_data = (object)$data;
            ob_start();
            include($this->get_views_file($view_name));
            $content_html = ob_get_contents();
            ob_end_clean();
            echo $this->set_views($content_html, $data);
            unset($render_data);
        } catch (\Exception $error) {
            MPHP::show_error_server(500, $error);
        }
    }

    private function set_views(string $contains, array $input = []): string {
        foreach ($input as $k => $v) {
            $contains = str_replace("#[$k]", $v, $contains);
        }
        return $contains;
    }

}
