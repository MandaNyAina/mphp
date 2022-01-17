<?php
    # configuration
    require_once __DIR__ . "/../config/Constant.php";

    # interface implementation
    require 'Interfaces/DatabaseInterface.php';
    require 'Interfaces/DirectoryInterface.php';
    require 'Interfaces/MessageInterface.php';
    require 'Interfaces/RouteRequestInterface.php';
    require 'Interfaces/UploaderInterface.php';

    # main interface implementation
    require 'Interfaces/CoreInterface/ControllerInterface.php';
    require 'Interfaces/CoreInterface/ModelInterface.php';
    require 'Interfaces/CoreInterface/RouteInterface.php';
    require 'Interfaces/CoreInterface/ViewInterface.php';
    require 'Interfaces/CoreInterface/MphpCoreInterface.php';

    # dependencies
    require 'libs/Plugins/Database.php';
    require 'libs/Plugins/Directory.php';
    require ROOT_PATH . '/helpers/default_helper.php';
    require ROOT_PATH . "/config/InitDb.php";

    # main injection
    require 'libs/Main/MainModels.php';
    require 'libs/Main/MainViews.php';
    require 'libs/Main/MainControllers.php';
    require 'libs/Main/MphpCore.php';
    require 'libs/Main/MainRoutes.php';

    # include autoload files
    require ROOT_PATH . "/core/includes.php";
    if (isset($models)) {
        foreach ($models as $model) {
            include ROOT_PATH . '/src/Models/' . "$model.php";
        }
    }

    if (isset($helpers)) {
        foreach ($helpers as $helper) {
            include ROOT_PATH . '/helpers/' . "$helper.php";
        }
    }

    # modules
    require 'libs/Plugins/Message.php';
    require 'libs/Plugins/Uploader.php';
    if (CONFIG['type_app'] === "FULL") {
        require 'libs/ViewsLoadModules.php';
    }

    # routing
    require ROOT_PATH . "/routes/Routes.php";

    # load app
    require ROOT_PATH . "/vendor/autoload.php";
