<?php

namespace Continy\Core;

use stdClass;
use Continy\Core\Route;

class Request
{

    private $method;
    public $path;
    public $query;
    public $input;
    private $route;

    public function __construct()
    {
        $this->method = self::method();
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function assignData(Route $route)
    {

        $this->route = $route;

        $this->assignPathData($route);
        $this->assignQueryData();
        $this->assignInputData();
        //$this->assignFileData();
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function assignPathData(Route $route)
    {
        $keys = $this->getKeysFromRoute($route);
        $vals = $this->getValsFromRoute($route);

        $comb = array_combine($keys, $vals);

        if (count($comb) > 0) {
            $this->path = json_decode(json_encode($comb, JSON_UNESCAPED_UNICODE), false);
        } else {
            $this->path = new stdClass;
        }

        return $this->path;
    }

    public function assignQueryData()
    {
        if (count($_GET) > 0) {
            $this->query = json_decode(json_encode($_GET, JSON_UNESCAPED_UNICODE), false);
        } else {
            $this->query = new stdClass;
        }
        return $this->query;
    }

    public function assignInputData()
    {
        if (self::method() == 'GET') {
            $this->input = $this->assignQueryData();
        } else {
            if (count($_POST) > 0) {
                if ($this->checkCSRF()) {
                    $post = $_POST;
                    unset($post['__HTTP_Method']);
                    unset($post['__CSRF_Token']);
                    $this->input = json_decode(json_encode($post, JSON_UNESCAPED_UNICODE), false);
                } else {
                    die('CSRF Error');
                }
            } else {
                $this->input = new stdClass;
            }
        }
        return $this->input;
    }

    public function checkCSRF()
    {
        if ($this->route->useCSRF()) {
            if (isset($_POST['__CSRF_Token'])) {
                return ($_POST['__CSRF_Token'] == $_SESSION['__CSRF_Token']) ? true : false;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    //public function assignFileData(){
    //TO DO
    //}

    private function getKeysFromRoute(Route $route)
    {

        preg_match_all('/(\{.+?\})/', $route->getPattern(true), $pattern_matches);

        if (count($pattern_matches) > 0) {
            $keys = [];
            foreach ($pattern_matches[0] as $raw_key) {
                $key = str_replace(['{', '}'], '', $raw_key);
                $keys[] = $key;
            }
            return $keys;
        } else {
            return [];
        }
    }

    private function getValsFromRoute(Route $route)
    {
        $pattern = preg_replace('/(\{.+?\})/', '(.+)', $route->getPattern(true));
        $regex = '~^' . $pattern . '/?$~i';

        preg_match($regex, currentPath(), $matches);

        $vals = [];

        if (count($matches)) {
            $match_n = count($matches);

            for ($i = 1; $i < $match_n; $i++) {
                $vals[] = $matches[$i];
            }

            return $vals;
        } else {
            return [];
        }
    }

    public function pVal($key = '', $type = 'default')
    {
        return $this->valConvert($key, $type, 'path');
    }

    public function qVal($key = '', $type = 'default')
    {
        return $this->valConvert($key, $type, 'query');
    }

    public function iVal($key = '', $type = 'default')
    {
        return $this->valConvert($key, $type, 'input');
    }

    private function valConvert($key = '', $type = 'default', $from)
    {
        if (empty($key)) {
            switch ($type) {

                case 'json':
                    return json_encode($this->$from, JSON_UNESCAPED_UNICODE);
                    break;
                case 'array':
                    return get_object_vars($this->$from);
                    break;
                default:
                    return $this->$from;
                    break;
            }
        } else {
            if (isset($this->$from->$key)) {
                switch ($type) {
                    case 'integer':
                    case 'int':
                        return intval($this->$from->$key);
                        break;
                    case 'float':
                        return floatval($this->$from->$key);
                        break;
                    case 'boolean':
                    case 'bool':
                        $val = $this->$from->$key;
                        if (is_numeric($val)) {
                            return ($val > 0) ? true : false;
                        } elseif (is_string($val)) {
                            $str_bool = strtolower($val);
                            if ($str_bool == 'true') {
                                return true;
                            } else {
                                return false;
                            }
                        } else {
                            return false;
                        }
                        break;
                    case 'json':
                        $jObj = new stdClass;
                        $jObj->$key = $this->$from->$key;
                        return json_encode($jObj);
                        break;
                    case 'array':
                        return [$key => $this->$from->$key];
                        break;
                    case 'raw':
                        return $this->$from->$key;
                        break;
                    case 'url':
                        return filter_var($this->$from->$key, FILTER_SANITIZE_URL);
                        break;
                    case 'email':
                        return filter_var($this->$from->$key, FILTER_SANITIZE_EMAIL);
                        break;
                    case 'tolower':
                        $str = filter_var($this->$from->$key, FILTER_SANITIZE_STRING);
                        $str = strtolower($str);
                        return strval($str);
                        break;
                    case 'toupper':
                        $str = filter_var($this->$from->$key, FILTER_SANITIZE_STRING);
                        $str = strtoupper($str);
                        return strval($str);
                        break;
                    case 'str':
                    case 'string':
                    case 'default':
                    default:
                        $str = filter_var($this->$from->$key, FILTER_SANITIZE_STRING);
                        return strval($str);
                        break;
                }
            } else {
                return NULL;
            }
        }
    }

    public static function method()
    {
        $request_method = strtoupper($_SERVER['REQUEST_METHOD']);
        $allow = Config::get('app/methods');

        if ($request_method == 'GET') {
            return 'GET';
        } elseif ($request_method == 'POST') {
            if (isset($_POST['__HTTP_Method'])) {
                if (in_array($request_method, $allow)) {
                    return strtoupper($request_method);
                } else {
                    die('Request method is not allowed');
                }
            } else {
                return 'POST';
            }
        } else {
            die('Unknown request method');
        }
    }

}
