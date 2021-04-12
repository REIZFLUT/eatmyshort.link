<?php

namespace Continy\Core;

use Continy\Core\Router;
use Continy\Core\Config;
use Continy\Core\MySQL;
use Continy\Core\Request;
use stdClass;

class App
{

    private static $MySQL;

    public function __construct()
    {
        Session::init();
        Language::init();

        MySQL::init(
            Config::get('mysql/host'),
            Config::get('mysql/port'),
            Config::get('mysql/database'),
            Config::get('mysql/user'),
            Config::get('mysql/password')
        );
    }

    public function setTimezone($identifier){
        date_default_timezone_set($identifier);
    }

    public function run()
    {
        if (is_file(Config::get('paths/routes'))) {
            $available_routes = require_once Config::get('paths/routes');
        } else {
            die('No Routes available');
        }

        Router::run(new Request, $available_routes);
    }
}
