<?php

namespace App\Http;

use Continy\Core\Request;
use Continy\Core\Config;

class DefaultController
{


    public function index(Request $request)
    {
    }

    public function languageSwitch(Request $request)
    {
        $available = Config::get('language/supported');

        if (in_array($request->pVal('lng', 'tolower'), $available)) {
            $_SESSION['LANGUAGE'] = $request->pVal('lng', 'tolower');
        }

        if (!empty($request->qVal('return'))) {
            redirect($request->qVal('return', 'url'));
        }
    }

    public function Http403(Request $request)
    {
        http_response_code(403);
        echo '403';
    }

    public function Http404(Request $request)
    {
        http_response_code(404);
        echo '404';
    }


    public function ApiHttp403(Request $request)
    {
        http_response_code(403);
        return view_json(['code' => 403, 'message' => 'Request not authorized']);
    }

    public function ApiHttp404(Request $request)
    {
        http_response_code(404);
        return view_json(['code' => 404, 'message' => 'Route not found']);
    }
}
