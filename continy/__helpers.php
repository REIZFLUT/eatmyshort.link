<?php

use Continy\Core\Config;
use Continy\Core\Request;
use Continy\Core\Session;
use Continy\Core\MySQL;
use Continy\Plugin\BeautifyHTML;

if (!function_exists('baseDir')) {
    function baseDir($path = '/')
    {
        $baseDir = __DIR__ . '/..';
        return $baseDir . $path;
    }
}

if (!function_exists('resourcesDir')) {
    function resourcesDir($path = '/')
    {
        return __DIR__ . '/../resources' . $path;
    }
}


if (!function_exists('currentPath')) {
    function currentPath()
    {
        if (isset($_SERVER['PATH_INFO'])) {
            $pathInfo = $_SERVER['PATH_INFO'];

            $last = substr($pathInfo, -1);
            if ($last == '/') {
                return trim(substr(filter_var($pathInfo, FILTER_SANITIZE_URL), 0, -1));
            } else {
                return trim(filter_var($pathInfo, FILTER_SANITIZE_URL));
            }
        } else {
            return '/';
        }
    }
}


if (!function_exists('redirect')) {
    function redirect($path = '/', $data = [], $oldVal = [])
    {

        if (count($data) > 0) {
            Session::next($data, $oldVal);
        }

        $fullpath = Config::get('app/url') . $path;
        return header('Location: ' . $fullpath);
    }
}

if (!function_exists('pluralize')) {
    function pluralize($word)
    {
        $last_letter = strtolower($word[strlen($word) - 1]);

        switch ($last_letter) {
            case 'y':
                return substr($word, 0, -1) . 'ies';
            case 's':
                return $word . 'es';
            default:
                return $word . 's';
        }
    }
}

if (!function_exists('baseUrl')) {
    function baseUrl($atRoot = FALSE, $atCore = FALSE, $parse = FALSE)
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $http = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
            $hostname = $_SERVER['HTTP_HOST'];
            $dir =  str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);

            $core = preg_split('@/@', str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath(dirname(__FILE__))), NULL, PREG_SPLIT_NO_EMPTY);
            $core = $core[0];

            $tmplt = $atRoot ? ($atCore ? "%s://%s/%s/" : "%s://%s/") : ($atCore ? "%s://%s/%s/" : "%s://%s%s");
            $end = $atRoot ? ($atCore ? $core : $hostname) : ($atCore ? $core : $dir);
            $base_url = sprintf($tmplt, $http, $hostname, $end);
        } else $base_url = 'http://localhost/';

        if ($parse) {
            $base_url = parse_url($base_url);
            if (isset($base_url['path'])) if ($base_url['path'] == '/') $base_url['path'] = '';
        }
        return substr($base_url, 0, -1);
        //return $base_url;
    }
}

if (!function_exists('view_template')) {
    function view_template($template)
    {
        $template_path = ResourcesDir('/Views/Templates/' . $template . '.tpl.php');

        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            $template_str = ob_get_clean();
            return $template_str;
        } else {
            return '';
        }
    }
}

if (!function_exists('view')) {
    function view(string $layout, string $template, array $data = [], bool $to_string = false)
    {
        extract($data);
        $layout_path = ResourcesDir('/views/layouts/' . $layout . '.layout.php');
        $template_path = ResourcesDir('/views/templates/' . $template . '.tpl.php');

        $debug = debug_backtrace();


        if (file_exists($layout_path)) {

            ob_start();
            include $layout_path;
            $layout_str = ob_get_clean();

            if (file_exists($template_path)) {

                ob_start();
                include $template_path;
                $template_str = ob_get_clean();

                preg_match_all('/<zone\:(.*?)>(.*?)<\/zone:(.*?)>/is', $template_str, $matches);

                $tag_matches_n = count($matches[0]);

                if ($tag_matches_n > 0) {
                    $replace = [];
                    for ($i = 0; $i < $tag_matches_n; $i++) {
                        $replace['tags'][]       = '<zone:' . $matches[1][$i] . ' />';
                        $replace['contents'][]   = $matches[2][$i];
                    }
                    if (Config::get('app/mode') == 'debug') {
                        $replace['tags'][] = '<zone:debug />';
                        $replace['contents'][] = "<!-- \nTemplate: {$template}\nLayout:{$layout}\nBacktrace: {$debug[0]['file']}\n-->";
                    } else {
                        $replace['tags'][] = '<zone:debug />';
                        $replace['contents'][] = '';
                    }

                    $output_prepare = str_replace($replace['tags'], $replace['contents'], $layout_str);
                }

                $output = preg_replace('/<zone\:(.*?) \/>/is', '', $output_prepare);

                $beauty = new BeautifyHTML();
                if ($to_string) {
                    return $beauty->beautify($output);
                } else {
                    echo $beauty->beautify($output);
                }
            } else {
                if ($to_string) {
                    return 'Template could not be found';
                } else {
                    echo 'Template could not be found';
                }
            }
        } else {
            if ($to_string) {
                return 'Layout could not be found';
            } else {
                echo 'Layout could not be found';
            }
        }
    }
}

if (!function_exists('text')) {
    function text(string $template, array $data, $allowHtml = true)
    {
        extract($data);
        $output = '';
        $template_path = ResourcesDir('/texts/' . $template . '.text.php');
        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            $output = ob_get_clean();
        } else {
            return 'No text template found.';
        }
        return ($allowHtml) ? $output:strip_tags($output);
    }
}

if (!function_exists('json')) {
    function view_json($data, int $options = 0)
    {
        header("Content-type: application/json; charset=utf-8");
        echo json_encode($data, $options);
    }
}


if (!function_exists('route_real_match')) {
    function route_real_match($m)
    {
        $n = count($m);
        if ($n > 0) {

            for ($i = 1; $i < $n; $i++) {
                if (strpos($m[$i], '/')) {
                    return false;
                }
            }
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('ctrlRunner')) {
    function ctrl_runner(string $ctrlmeth, Request $request)
    {
        list($controller, $method) = explode('@', $ctrlmeth);
        $id = (isset($request->path->id)) ? $request->path->id : NULL;
        $ctrlName = 'App\\Http\\' . $controller;
        $ctrl = new $ctrlName();
        return $ctrl->$method($request, $id);
    }
}

if (!function_exists('dd')) {
    function dd($var)
    {
        var_dump($var);
        die();
    }
}
