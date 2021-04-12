<?php

namespace Continy\Core;

class RouteError
{
    private $code;
    private $requested_path;
    private $route_groups;

    public function __construct(int $code, array $routes, Request $request)
    {
        $this->code = $code;
        $this->request = $request;
        $this->route_groups = $routes;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function showErrorInContext()
    {
        $path_parts = explode('/', substr(currentPath(), 1));
        $default_error_handler = '';
        $error_handler = '';
        if (isset($path_parts[0])) {
            foreach ($this->route_groups as $route_group) {
                if (isset($route_group['prefix'])) {
                    if ($route_group['prefix'] == '') {
                        if (isset($route_group['http' . $this->code])) {
                            $default_error_handler = (empty($default_error_handler)) ? $route_group['http' . $this->code] : $default_error_handler;
                        }
                    } else {
                        if ($path_parts[0] == $route_group['prefix'] && isset($route_group['http' . $this->code])) {
                            $error_handler = $route_group['http' . $this->code];
                        }
                    }
                } else {
                    if (isset($route_group['http' . $this->code])) {
                        $default_error_handler = (empty($default_error_handler)) ? $route_group['http' . $this->code] : $default_error_handler;
                    }
                }
            }

            $actual_error_handler = (empty($error_handler)) ? $default_error_handler : $error_handler;
            return $this->handleError($actual_error_handler, $this->request);
        } else {
            http_response_code($this->getCode());
            return '';
        }
    }

    private function handleError(string $handle, Request $request)
    {
        $handle_parts = explode(':', $handle);

        if (strtolower($handle_parts[0]) == 'controller') {
            return ctrl_runner($handle_parts[1], $request);
        } elseif ($handle_parts[0] == 'redirect') {
            return redirect($handle_parts[1]);
        } elseif ($handle_parts[0] == 'logout_redirect') {
            $userModel = Config::get('auth/userModel');
            $user = new $userModel;
            Auth::logout($user);
            return redirect($handle_parts[1]);
        } else {
            http_response_code($this->getCode());
            die();
        }
    }
}
