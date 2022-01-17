<?php

namespace Core\Lib\Main;
use Core\Interfaces\RouteRequestInterface;
use Core\Interfaces\CoreInterface\RouteInterface;
use Core\Lib\Main\MphpCore as MPHP;

class MainRoutes implements RouteInterface {
    private ?string $method = null;
    private ?string $path_root = null;
    private array $instanced_method = [];
    private array $route_lists = [];
    protected $headers = null;

    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path_root = @explode("?", $_SERVER['REQUEST_URI'])[0];
        $this->headers = getallheaders();
    }

    private function call_controller(RouteRequestInterface $request, string $controller) {
        $directory_manager = new \Core\Lib\Plugins\Directory();
        $controller = @explode("#", $controller);
        $controller_path = $directory_manager->controllers("", true);
        try {
            $controller_file = MPHP::get_file_in_folder($controller_path, "{$controller[0]}.php");
            if ($controller_file === null) { throw new \ValueError("Controller : {$controller[0]} not found"); }
            $namespace_value = MPHP::get_namespace_by_php_file($controller_path, $controller_file);
            $controller_instance = MPHP::call_dynamic_class("\\App\\Controllers\\" . $namespace_value . $controller[0], $controller_file);
            $method_of_controller = $controller[1];
            $controller_instance->$method_of_controller($request);
        } catch (\ValueError $error) {
            MPHP::show_error_server(500, $error);
        }
    }

    private function  add_to_route_lists(string $route) {
        array_push($this->route_lists, $route);
    }

    private function get_params_value(): object {
        $uri_query = @explode("?", $_SERVER["REQUEST_URI"])[1];
        $query = @explode("&", $uri_query);
        $get_data = [];
        foreach ($query as $q) {
            $q = @explode("=", $q);
            if (!empty($q)) {
                $get_data[$q[0]] = @$q[1];
            }
        }
        return (object)$get_data;
    }

    private function get_post_input(): object {
        switch (explode(";", $this->headers["Content-Type"])[0]) {
            case 'application/json':
                $input_content = file_get_contents('php://input');
                return !empty($input_content) ? (object)json_to_array($input_content) : (object)[];
            case 'multipart/form-data':
                $_SERVER['FILES'] = (object)($_FILES);
                return (object)($_POST);
            default:
                return (object)[];
        }
    }

    private function create_controller(object $server_request, string $controller_name): void {
        $RouteRequestParams = new RouteRequestInterface($server_request);
        $this->call_controller($RouteRequestParams, $controller_name);
    }

    public function get(string $route, string $controller): void {
        $this->add_to_route_lists($route);
        if ($route === $this->path_root && !in_array([$route, 'GET'], $this->instanced_method) && $this->method === 'GET') {
            array_push($this->instanced_method, [$route, 'GET']);
            $_SERVER["GET"] = $this->get_params_value();
            $this->create_controller((object)$_SERVER, $controller);
        }
    }

    public function post($route, string $controller): void {
        $this->add_to_route_lists($route);
        if ($route === $this->path_root && !in_array([$route, 'POST'], $this->instanced_method) && $this->method === 'POST') {
            array_push($this->instanced_method, [$route, 'POST']);
            $_SERVER["POST"] = $this->get_post_input();
            $this->create_controller((object)$_SERVER, $controller);
        }
    }

    public function put($route, string $controller): void {
        $this->add_to_route_lists($route);
        if ($route === $this->path_root && !in_array([$route, 'PUT'], $this->instanced_method) && $this->method === 'PUT') {
            array_push($this->instanced_method, [$route, 'PUT']);
            $_SERVER["PUT"] = $this->get_post_input();
            $this->create_controller((object)$_SERVER, $controller);
        }
    }

    public function delete(string $route, string $controller): void {
        $this->add_to_route_lists($route);
        if ($route === $this->path_root && !in_array([$route, 'DELETE'], $this->instanced_method) && $this->method === 'DELETE') {
            array_push($this->instanced_method, [$route, 'DELETE']);
            $_SERVER["GET"] = $this->get_params_value();
            $this->create_controller((object)$_SERVER, $controller);
        }
    }

    public function __destruct() {
        if (!in_array([$this->path_root, $this->method], $this->instanced_method)) {
            $exist_route = false;
            foreach ($this->route_lists as $each_route) {
                if ($each_route === $this->path_root) {
                    $exist_route = true;
                }
            }
            if ($exist_route) {
                http_response_code(405);
                echo array_to_json([
                    "message" => "Invalid method",
                    "status" => 405
                ]);
            } else {
                http_response_code(404);
                MPHP::show_error_server(404);
            }
        }
        $this->route_lists = [];
        $this->instanced_method = [];
        $this->method = null;
        $this->headers = null;
    }

}
