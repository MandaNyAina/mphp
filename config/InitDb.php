<?php

use Core\Lib\Plugins\Database;
use \Core\Interfaces\DatabaseInterface;

class InitDb {
    private static ?DatabaseInterface $database = null;

    protected function set_database() {
        if (self::$database === null) {
            $config = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/config/config.ini');
            $host = $config['db_host'];
            if (strtolower($host) === "localhost") {
                $host = "127.0.0.1";
            }
            self::$database = new Database($config['db_driver'], $host, $config['db_name'], $config['db_port'], $config['db_user'], $config['db_password']);
        }
    }

    protected function get_database(): ?DatabaseInterface {
        return self::$database;
    }
}