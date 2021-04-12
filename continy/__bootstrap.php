<?php

//error_reporting(E_ALL);

require __DIR__ . '/__autoloader.php';
require __DIR__ . '/__helpers.php';

$composer_autoloader =  __DIR__ . '/../vendor/autoload.php';

if (is_file($composer_autoloader)) {
    require $composer_autoloader;
}
