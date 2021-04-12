<?php

use App\Models\EMSLConfig;
use Continy\Core\Notification;
use Continy\Core\Html;
use Continy\Core\Config;
use Continy\Core\Language;

?>

<zone:head>

</zone:head>
<?= view_template('Emsl/mainnavi'); ?>
<zone:body>
    <main>
    
        <div id="settingsList" class="container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Einstellung</th>
                        <th>Wert</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?= HTML::autoTableRows($list->result, [
                        'k' => [],
                        'v' => [],
                        'id*edit'   => ['link_route' => '/setting/edit/{id}',   'replace' => Language::get('emsl/action/edit')],
                    ]) ?>
                </tbody>
            </table>
        </div>
    


    </main>

</zone:body>