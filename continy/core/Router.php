<?php

namespace Continy\Core;

use Continy\Core\Request;
use Continy\Core\Auth;
use App\Models\Token;
use App\Models\UserBlock;

class Router
{

    private $routes;


    public static function run(Request $request, array $available_routes)
    {
        $route = self::findRoute($available_routes, $request);

        if (is_a($route, '\Continy\Core\Route')) {
            return self::execute($route, $request, $available_routes);
        } else {
            return self::routeError($route, $request);
        }
    }


    private static function findRoute(array $available_routes, $request)
    {

        $routes_found = [];

        foreach ($available_routes as $route_group_name => $route_group_args) {
            $route_group = new RouteGroup($route_group_name, $route_group_args);
            foreach ($route_group->getMatchingRoutes() as $route) {
                $routes_found[] = $route;
            }
        }

        if (count($routes_found) > 1) {
            return new RouteError(502, $available_routes, $request);
        } elseif (count($routes_found) == 0) {
            return new RouteError(404, $available_routes, $request);
        } else {
            return $routes_found[0]; // is a Route Object
        }
    }

    private static function routeError(RouteError $routeError, Request $request)
    {
        return $routeError->showErrorInContext($request);
    }

    private static function execute(Route $route, Request $request, $available_routes)
    {
        $request->assignData($route);

        Token::cleanUp();
        UserBlock::cleanup();

        if (Auth::guard($route, $request)) {
            return ctrl_runner($route->ctrlmeth, $request);
        } else {
            $routeError = new RouteError(403, $available_routes, $request);
            return $routeError->showErrorInContext($request);
        }
    }
}
