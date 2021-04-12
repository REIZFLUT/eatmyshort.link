<?php

namespace Continy\Core;

class RouteGroup
{

    public $name;
    public $routes;
    public $prefix;
    public $http404;
    public $http403;
    private $auth;
    private $csrf;


    public function __construct(string $name, array $args)
    {
        $this->name    = $name;
        $this->args    = $args;
        $this->prefix  = (!empty($args['prefix'])) ? '/' . $args['prefix'] : '';
        $this->http404 = (isset($args['http404'])) ? $args['http404'] : '';
        $this->http403 = (isset($args['http403'])) ? $args['http403'] : '';
        $this->routes  = (isset($args['routes'])) ? $args['routes'] : [];
        $this->auth    = (isset($args['auth'])) ? $args['auth'] : [];
        $this->csrf    = (isset($args['csrf'])) ? $args['csrf'] : true;
        $this->session = (isset($args['session'])) ? $args['session']:true;
    }

    public function getMatchingRoutes()
    {
        $matching_routes = [];

        foreach ($this->routes as $route) {
            if (count($route) > 2) {
                if (strtoupper($route[0]) == Request::method()) {

                    $route_pattern = $this->prefix . $route[1];

                    $pattern = preg_replace('/(\{.+?\})/', '(.+)', $route_pattern);
                    preg_match('~^' . $pattern . '/?$~i', currentPath(), $matches);

                    if (route_real_match($matches)) {
                        $matching_routes[] = new Route($route, $this->prefix, $this->http403, $this->auth, $this->csrf, $this->session);
                    }
                }
            }
        }

        return $matching_routes;
    }
}
