<?php

namespace Core\Interfaces\CoreInterface;

interface ViewInterface {
    public function load_views(string $view_name, array $page_details, array $data = []): void;
}