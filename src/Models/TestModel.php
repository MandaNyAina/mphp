<?php

namespace App\Models;
use \Core\Lib\Main\MainModels;

class TestModel extends MainModels {

    public function getDataInformations() {
        return $this->db->select('test_table');
    }

}
