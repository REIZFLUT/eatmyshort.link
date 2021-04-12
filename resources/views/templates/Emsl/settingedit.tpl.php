<?php

use Continy\Core\Notification;
use Continy\Core\Html;
use Continy\Core\Language;

?>

<?= view_template('Emsl/mainnavi'); ?>

<zone:body>
    <main id="setting">
        <div id="editSetting" class="center-360 py-20">
            <h3><?= Language::get('emsl/label/setting'); ?></h3>
            <?= Html::formPostStart('/setting/edit/' . $config->id); ?>
            <div class="input-wrapper">
                <?= Html::input('text', 'k',  $config->k, ['disabled' => 'disabled'], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::input('text', 'v', $config->v, [], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::submitBtn('save', ['class' => 'btn btn-block text-center btn-primary'], 'emsl'); ?>
            </div>
        </div>
        <div id="settingDescription" class="center-360 py-20">
            <?= Language::get('emsl/description/setting/' . $config->k); ?>
        </div>
    </main>

    <div class="toasts">
        <?= Notification::toast('success', 'toast fadeOut toast-success') ?>
    </div>
</zone:body>