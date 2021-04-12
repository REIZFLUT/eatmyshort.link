<?php

use Continy\Core\Notification;
use Continy\Core\Html;
use Continy\Core\Language;

?>

<?= view_template('Emsl/mainnavi'); ?>

<zone:body>
    <main id="myaccount">
        <div id="editMyAccount" class="center-360 py-20">
            <h3><?= Language::get('emsl/label/my-account'); ?></h3>
            <?= Html::formPostStart('/user/account'); ?>
            <div class="input-wrapper">
                <?= Html::input('email',    'email',        $user->email, ['disabled' => 'disabled'], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::input('text',     'firstname',    $user->firstname, [], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::input('text',     'lastname',     $user->lastname, [], 'emsl'); ?>
            </div>
            <div class="my-20">
                <h4><?= Language::get('emsl/label/change-password'); ?></h4>
                <p><?= Language::get('emsl/description/change-password'); ?></p>
            </div>
            <div class="input-wrapper">
                <?= Html::input('password', 'password_old', '', [], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::input('password', 'password_new', '', [], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::input('password', 'password_cfm', '', [], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::submitBtn('save', ['class' => 'btn btn-block text-center btn-primary'], 'emsl'); ?>
            </div>
            <?= Html::formEnd(); ?>

            <hr>

            <div class="py-20">
                <?= Language::get('emsl/label/your-roles'); ?>:
                <ul class="pills my-20">
                    <?= Html::li($user->roles, ['class' => 'pill']); ?>
                </ul>
                <?= Language::get('emsl/description/your-roles'); ?>

            </div>
        </div>
    </main>

    <div class="toasts">
        <?= Notification::toast('fail_insecure', 'toast fadeOut toast-invalid'); ?>
        <?= Notification::toast('fail_missmatch', 'toast fadeOut toast-invalid'); ?>
        <?= Notification::toast('fail_old_password', 'toast fadeOut toast-invalid') ?>
        <?= Notification::toast('success', 'toast fadeOut toast-success') ?>
    </div>
</zone:body>