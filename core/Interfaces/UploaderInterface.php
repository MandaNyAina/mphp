<?php

namespace Core\Interfaces;

interface UploaderInterface {
    public function set_unauthorized_types(array $custom_unauthorized_upload_file): void;
    public function start(array $file, array $config, array $requirements): bool;
}