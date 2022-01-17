<?php

namespace App\Controllers\TestController;
use App\Models\TestModel;
use Core\Interfaces\RouteRequestInterface;
use \Core\Lib\Main\MainControllers;

class TestController extends MainControllers {

    private TestModel $test_model;

    public function __construct() {
        parent::__construct();
        $this->test_model = $this->get_model('TestModel');
    }

    public function get_name(RouteRequestInterface $req) {
        $data = $this->test_model->getDataInformations();
        $this->send_response("Get name with data in db", HTTP_OK, array_merge($data, [[ 'name' => $req->GET->name ]] ));
    }

    public function insert_name(RouteRequestInterface $req) {
        $this->send_response("Inserted data {$req->POST->firstname}", HTTP_OK);
    }

    public function update_name(RouteRequestInterface $req) {
        $this->send_response("Updated data {$req->PUT->id}", HTTP_OK);
    }

    public function delete_name(RouteRequestInterface $req) {
        $this->send_response("Deleted data {$req->GET->id}", HTTP_OK);
    }

}
