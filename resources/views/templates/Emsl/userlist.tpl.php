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
                        <th>E-Mail</th>
                        <th>Vorname</th>
                        <th>Nachname</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?= HTML::autoTableRows($list->result, [
                        'email' => [],
                        'firstname' => [],
                        'lastname'  => [],
                        'id*edit'   => ['link_route' => '/user/edit/{id}',   'replace' => Language::get('emsl/action/edit')],
                        'id*delete'   => ['link_route' => '/user/delete/{id}',   'replace' => Language::get('emsl/action/delete')],
                    ]) ?>
                </tbody>
            </table>
        </div>



    </main>

</zone:body>