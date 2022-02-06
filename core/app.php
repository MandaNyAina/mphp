<?php

class MPHPApp {

    private function configure_environment(string $env) {
        switch ($env) {
            case 'development':
                error_reporting(-1);
                ini_set('display_errors', 1);
                ini_set('max_execution_time', '300');
                break;
            
        case 'production':
            ini_set('display_errors', 0);
            break;
        
        default:
            header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
            echo 'The application environment is not set correctly.';
            exit(1);
        }
    }

    public function create_app(?array $need_callback = []) {
        require_once 'bootstrap.php';
        $this->configure_environment(CONFIG['mode_env']);
        foreach ($need_callback as $callback) {
            $callback();
        }
    }

}