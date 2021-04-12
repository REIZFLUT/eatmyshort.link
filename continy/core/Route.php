<?php

namespace Continy\Core;

class Route
{

    private $http_method;
    private $pattern;
    private $csrf;
    public $ctrlmeth;
    public $options;
    public $auth;
    public $prefix;


    public function __construct(array $route_args, string $prefix, string $http403, array $auth, bool $csrf)
    {
        $this->http_method = $route_args[0];
        $this->pattern = $route_args[1];
        $this->ctrlmeth = $route_args[2];
        $this->options = (isset($route_args[3])) ? $route_args[3] : [];
        $this->prefix = $prefix;
        $this->http403 = $http403;
        $this->auth = $auth;
        $this->csrf = $csrf;
    }

    public function getPattern($fullPattern = false)
    {
        if ($fullPattern) {
            return (empty($this->prefix)) ? $this->pattern : $this->prefix . $this->pattern;
        } else {
            return $this->pattern;
        }
    }

    public function isPublic()
    {
        $public = false;
        if (!isset($this->auth['user']) && !isset($this->auth['token'])) {
            return true;
        } else {

            if (isset($this->auth['user'])) {
                foreach ($this->auth['user'] as $role) {
                    if (strtolower($role) == 'public') return true;
                }
            }

            if (isset($this->auth['token'])) {
                foreach ($this->auth['token'] as $role) {
                    if (strtolower($role) == 'public') return true;
                }
            }
        }
    }

    public function getHttpMethod()
    {
        return $this->http_method;
    }

    public function useCSRF()
    {
        return $this->csrf;
    }

    public function authUser()
    {
        return isset($this->auth['user']) ? $this->auth['user'] : [];
    }

    public function authToken()
    {
        return isset($this->auth['token']) ? $this->auth['token'] : [];
    }

    public static function current()
    {
        return (isset($_SERVER['PATH_INFO'])) ? $_SERVER['PATH_INFO'] : '/';
    }
}
