<?php

namespace App\Http;

use App\Models\Count;
use App\Models\EMSLConfig;
use App\Models\Shortlink;
use App\Models\Localisation;
use App\Models\User;
use Continy\Core\Language;
use Continy\Core\Request;
use Continy\Core\Collection;
use Continy\Plugin\EMSLValidation;

class ShortlinkController
{

    public function index(Request $request)
    {

        $collection = new Collection('Shortlink');
        if (empty($request->iVal('q'))) {
            $sl_list = $collection->all(true, EMSLConfig::get('resultsPerPage'), 'p', 'ORDER BY id DESC');
        } else {
            $sl_list = $collection->searchLikeOr('q', ['from_path', 'to_url'], true, EMSLConfig::get('resultsPerPage'), 'p', 'ORDER BY id DESC');
        }

        foreach ($sl_list->result as $sl) {
            $sl->hasLocals();
            $sl->locals();
        }


        return view('default', 'Emsl/welcome', ['list' => $sl_list]);
    }

    public function edit(Request $request)
    {
    }

    public function update(Request $request)
    {
    }

    public function create(Request $request)
    {

        $validator = new EMSLValidation([
            'from_path' => ['required', 'emsl_path'],
            'to_url'    => ['required', 'url']
        ]);

        if ($validator->check($request)) {

            $keep = ['from_path' => $request->iVal('from_path'), 'to_url' => $request->iVal('to_url', 'url')];

            if (!Shortlink::pathExists($request->iVal('from_path'))) {

                $user = new User();
                $shortlink = new Shortlink();
                $shortlink->from_path = $request->iVal('from_path');;
                $shortlink->to_url    = $request->iVal('to_url', 'url');
                $shortlink->usr       = $user->id;

                $shortlink->create();
                redirect('/shortlink', ['successfullyCreated' => Language::get('emsl/success/created')]);
            } else {
                redirect('/shortlink', ['pathExists' => Language::get('emsl/error/path_exists')], $keep);
            }
        } else {
            $keep = [
                'from_path' => $request->iVal('from_path'),
                'to_url'    => $request->iVal('to_url', 'url')
            ];

            redirect('/shortlink', $validator->getErrorMessages(), $keep);
        }
    }


    public function forward(Request $request)
    {

        if (isset($_SERVER['PATH_INFO'])) {
            $path = substr($_SERVER['PATH_INFO'], 1);
            $lang = Language::shortFromBrowser();
            $url = '';

            $shortlink = new Shortlink(true);
            if ($shortlink->pathExists($path)) {
                $shortlink->loadFromPath($path);
                if ($shortlink->hasLocal($lang)) {
                    $localisation = new Localisation(true);
                    $localisation->loadSlidLng($shortlink->id, $lang);
                    $url = $localisation->to_url;
                } else {
                    $url = $shortlink->to_url;
                }

                $counter = new Count();
                $counter->sl_id = $shortlink->id;
                $counter->path = $path;
                $counter->auto();
            } else {
                if (EMSLConfig::bool('forwardAll')) {
                    $url = EMSLConfig::get('defaultDomain') . '/' . $path;
                } else {
                    http_response_code(404);
                    return view('public', 'Default/notfound');
                }
            }

            header('Location: ' . $url);
        } else {
            $default = EMSLConfig::get('defaultDomain');
            header('Location: ' . $default);
        }
    }
}
