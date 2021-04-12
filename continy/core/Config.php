<?php

namespace Continy\Core;

use Continy\Core\App;

class Config
{

    private static $configArray;

    public static function init($path = '')
    {
        if (!is_array(self::$configArray)) {
            $default_config_path = baseDir('/continy/__config.php');

            if (file_exists($default_config_path)) {
                $c_Arr = require_once $default_config_path;
            } else {
                $c_Arr = [];
            }

            $custom_config_path = (empty($path)) ? baseDir('/resources/config.php') : baseDir($path);

            if (file_exists($custom_config_path)) {
                $e_Arr = require_once $custom_config_path;
            } else {
                $e_Arr = [];
            }

            self::$configArray = array_replace_recursive($c_Arr, $e_Arr);
        }
    }

    public static function get($path = '')
    {

        self::init();

        if ($path == '') {
            return self::$configArray;
        } else {
            $keys = explode('/', $path);
            $array = self::$configArray;
            $result = NULL;

            foreach ($keys as $key) {
                $checked = self::check_recursive($array, $key);
                if (is_array($checked)) {
                    $array  = $checked;
                    $result = $checked;
                } else {
                    $result = $checked;
                }
            }
            return $result;
        }
    }

    private static function check_recursive($array, $key)
    {
        if (is_array($array)) {
            if (array_key_exists($key, $array)) {
                return $array[$key];
            } else {
                return NULL;
            }
        } else {
            return $array;
        }
    }
}
