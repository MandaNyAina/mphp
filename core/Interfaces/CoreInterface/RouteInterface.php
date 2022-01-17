<?php

namespace Core\Interfaces\CoreInterface;

interface RouteInterface {
    public function get(string $route, string $controller): void;
    public function post(string $route, string $controller): void;
    public function put(string $route, string $controller): void;
    public function delete(string $route, string $controller): void;
}