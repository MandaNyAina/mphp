<?php

use \Core\Lib\Main\MainRoutes;

$routes = new MainRoutes();

$routes->get("/get_name", "TestController#get_name");
$routes->post("/set_name", "TestController#insert_name");
$routes->put("/update_name", "TestController#update_name");
$routes->delete("/delete_name", "TestController#delete_name");