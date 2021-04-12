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
        <div id="mainNewShortlink">
            <div class="container">
                <?= Html::formPostStart('/shortlink/create') ?>
                <div class="form-input">
                    <?= Html::input(
                        'text',
                        'from_path',
                        '',
                        ['required' => 'required', 'pattern' => '[a-z0-9_-]+', 'placeholder' => EMSLConfig::get('shortDomain') . '/'],
                    ) ?>
                </div>
                <div class="form-input">
                    <?= Html::input('url', 'to_url', '', ['required' => 'required'], 'emsl') ?>
                </div>
                <div class="form-input">
                    <?= Html::submitBtn('create', [], 'emsl') ?>
                </div>
                <?= Html::formEnd() ?>
            </div>
        </div>


        <div class="container">
            <form method="GET" action="<?= Config::get('app/url') ?>/shortlink" enctype="application/x-www-form-urlencoded" class="searchForm">
                <?= Html::input('search', 'q', $_GET['q'] ?? '', ['class' => 'searchInput', 'onsearch' => 'checkClearSearch(this.value)'], 'emsl'); ?>
                <?= Html::submitBtn('search', ['class' => 'searchBtn'], 'emsl'); ?>
            </form>
        </div>


        <div class="container">
            <table class="table table-striped table-responsive">
                <thead>
                    <tr>
                        <th>Shortlink</th>
                        <th>Redirect to</th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    function localsShow($locals)
                    {
                        $output = '';

                        if (is_a($locals, 'Continy\Core\Collection')) {
                            foreach ($locals->getResult() as $local) {
                                $output .= "\n\t" . '<a href="' . Config::get('app/url') . '/shortlink/localisation/edit/' . $local->id . '" title="' . $local->to_url . '">' . $local->lng . '</a>' . "\n";
                            }
                        }
                        return $output;
                    }

                    echo Html::autoTableRows(
                        $list->result,
                        [
                            'from_path' => [],
                            'to_url'    => ['link_external' => '{self}'],
                            'id*edit'   => ['link_route' => '/shortlink/edit/{id}',   'replace' => Language::get('emsl/action/edit')],
                            'id*delete' => ['link_route' => '/shortlink/delete/{id}', 'replace' => Language::get('emsl/action/delete')],
                            'locals'    => ['callback' => 'localsShow']
                        ]
                    ); ?>
                </tbody>
            </table>
            <?= Html::simplePagination($list->pagination['max'], $list->pagination['query']); ?>
        </div>
    </main>


    <div class="toasts">
        <?= Notification::toast('from_path', 'toast fadeOut toast-invalid'); ?>
        <?= Notification::toast('to_url', 'toast fadeOut toast-invalid'); ?>
        <?= Notification::toast('pathExists', 'toast fadeOut toast-invalid') ?>
        <?= Notification::toast('successfullyCreated', 'toast fadeOut toast-success') ?>
    </div>

    <script>
        // Clear Search Button
        function checkClearSearch(value) {
            if (value == '') {
                window.location = '<?= Config::get('app/url') ?>/shortlink';
            }
        }
    </script>

</zone:body>