<?php

namespace App\Http;

use App\Models\EMSLConfig;
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

        return view('default', 'emsl/settingedit', ['config' => $conf]);
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
