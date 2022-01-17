<?php

namespace Core\Interfaces\CoreInterface;

interface ControllerInterface {
    public function send_response(string $message, int $status, $data = null): void;
    public function get_model(string $model_name);
    public function render(string $views_file, array $data = []): void;
}