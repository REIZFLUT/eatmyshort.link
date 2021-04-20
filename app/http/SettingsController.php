<?php

namespace App\Http;

use App\Models\EMSLConfig;
use App\Models\Shortlink;
use Continy\Core\Request;
use Continy\Core\Collection;

class SettingsController
{

    public function index(Request $request)
    {
        $collection = new Collection('EMSLConfig');
        $config_list = $collection->all(false, 0, '', 'ORDER BY k ASC');

        return view('default', 'emsl/settingslist', ['list' => $config_list]);
    }

    public function edit(Request $request, $id)
    {
        $id = intval($id);

        $conf = new EMSLConfig();
        $conf->findById($id);

        $shortlink = new Shortlink(true);
        $shortlink->findById($id);
       
        return view('default', 'emsl/settingedit', ['config' => $conf, 'shortlink' => $shortlink]);
    }

    public function update(Request $request, $id)
    {
        $id = intval($id);
        $conf = new EMSLConfig();
        $conf->findById($id);
        $conf->v = $request->iVal('v');
        $conf->update();
        return redirect('/setting/edit/' . $id, ['success' => 'Setting successfully updated']);
    }
}
