<?php

namespace Core\Lib\Main;
use Core\Interfaces\CoreInterface\ControllerInterface;
use Core\Interfaces\CoreInterface\ModelInterface;
use Core\Interfaces\CoreInterface\ViewInterface;

class MainControllers  implements ControllerInterface {
    private ?ModelInterface $models_instance = null;
    private ?ViewInterface $views_instance = null;
    protected $headers = null;

    public function __construct() {
        $this->models_instance = new MainModels();
        $this->views_instance = new MainViews();
        $this->headers = getallheaders();
    }

    public function send_response(string $message, int $status, $data = null): void {
        $response_data = [
            "message" => $message,
            "status" => $status
        ];
        if ($data) {
            $response_data["data"] = $data;
        }
        http_response_code($status);
        echo array_to_json($response_data);
    }

    public function get_model(string $model_name) {
        return $this->models_instance->load_models($model_name);
    }

    public function render(string $views_file, array $data = []): void {
        $this->views_instance->load_views($views_file, $data);;
    }

}
