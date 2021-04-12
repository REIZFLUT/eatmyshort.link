<?php

function continy_autoloader($class)
{

    $error_msg = 'Could not load ' . $class . '. It does not exist in Continy.';

    $class_path  = str_replace('\\', '/', $class);
    $class_array = explode('/', $class_path);
    $class_name  = array_pop($class_array);
    $class_array = array_map('strtolower', $class_array);
    $class_array[] = $class_name;
    $class_real_path = implode('/', $class_array);
    $path = baseDir('/' . $class_real_path . '.php');

    if (file_exists($path)) {
        require_once $path;
    } else {
        die($error_msg);
    }
}

spl_autoload_register('continy_autoloader');
