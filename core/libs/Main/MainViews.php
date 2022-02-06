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

    private function set_content_view(array $page_details, string $content): string {
        $page_name = $page_details['page_name'] ?? 'Document';
        $style_file_path = $page_details['css_file'] ?? null;
        $js_file_path = $page_details['js_file'] ?? null;

        return "<script>
            const createLinkCssElement = (path) => {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = path;
                return link;
            }
            
            const createScriptElement = (path) => {
                const script = document.createElement('script');
                script.src = path;
                script.type = 'module';
                return script;
            }
            
            window.addEventListener('DOMContentLoaded', function() {
                const head = document.getElementsByTagName('head');
                head[0].appendChild(createLinkCssElement('/assets/css/global.css'));
                ".($style_file_path ? 'head[0].appendChild(createLinkCssElement(\''.$style_file_path.'\'))' : '')."
            })
            
            window.addEventListener('load', function() {                
                document.body.innerHTML = '$content';
                const head = document.getElementsByTagName('head');
                head[0].appendChild(createScriptElement('/assets/js/script.js'));
                ".($js_file_path ? 'head[0].appendChild(createScriptElement(\''.$js_file_path.'\'))' : '')."
                const title = document.createElement('title');
                title.innerHTML = '{$page_name}';
                head[0].appendChild(title);
                head[0].getElementsByTagName('script')[0].remove();
            })
        </script>";
    }

    public function load_views(string $view_name, array $page_details, array $data = []): void {
        try {
            $render_data = (object)$data;
            ob_start();
            include($this->get_views_file($view_name));
            $content_html = ob_get_contents();
            ob_end_clean();
            echo $this->set_content_view($page_details, $this->set_views($content_html, $data));
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
