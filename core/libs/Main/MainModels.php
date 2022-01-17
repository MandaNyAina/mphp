<?php

namespace Core\Lib\Main;
use InitDb;
use Core\Interfaces\DatabaseInterface;
use Core\Interfaces\CoreInterface\ModelInterface;
use Core\Interfaces\DirectoryInterface;
use Core\Lib\Plugins\Directory;
use Core\Lib\Main\MphpCore as MPHP;

class MainModels extends InitDb implements ModelInterface {
    private ?DirectoryInterface $directory_manager = null;
    protected ?DatabaseInterface $db = null;

    function __construct() {
        $this->set_database();
        $this->db = $this->get_database();
        $this->directory_manager = new Directory();
    }


    public function load_models(string $model_name) {
        $model_path = $this->directory_manager->models("", true);
        try {
            $model_file = MPHP::get_file_in_folder($model_path, "$model_name.php");
            if ($model_file === null) { throw new \ValueError("Model : {$model_name} not found"); }
            $namespace_value = MPHP::get_namespace_by_php_file($model_path, $model_file);
            return MPHP::call_dynamic_class("\\App\\Models\\" . $namespace_value . $model_name, $model_file);
        } catch (\ValueError $e) {
            MPHP::show_error_server("/500", $e);
        }
        return null;
    }

}
