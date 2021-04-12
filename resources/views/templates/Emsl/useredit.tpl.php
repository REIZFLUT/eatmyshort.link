<?php

use Continy\Core\Notification;
use Continy\Core\Html;
use Continy\Core\Language;

$pwd_require = ($create) ? ['required' => 'required'] : [];

?>

<?= view_template('Emsl/mainnavi'); ?>

<zone:body>
    <main id="myaccount">
        <div id="editMyAccount" class="center-360 py-20">
            <h3><?php echo ($create) ?  Language::get('emsl/label/user-account-create') : Language::get('emsl/label/user-account') . ' #' . $user->id; ?></h3>
            <?= Html::formPostStart($postTo); ?>
            <div class="input-wrapper">
                <?= Html::input('email',    'email',        $user->email ?? '', ['required' => 'required'], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::input('text',     'firstname',    $user->firstname ?? '', [], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::input('text',     'lastname',     $user->lastname ?? '', [], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::input('password', 'password_new', '', $pwd_require, 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::input('password', 'password_cfm', '', $pwd_require, 'emsl'); ?>
            </div>
            <hr>
            <div class="py-20">
                <h4><?= Language::get('emsl/label/user-roles'); ?></h4>
                <div class="checkbox-group-wrapper">

                    <label><?= Html::checkbox('user', 'user', $user->roleIfHas('user')) ?>User</label>
                    <label><?= Html::checkbox('manager', 'manager', $user->roleIfHas('manager')) ?>Manager</label>
                    <label><?= Html::checkbox('admin', 'admin', $user->roleIfHas('admin')) ?> Admin</label>

                </div>
            </div>
            <hr>

            <div class="input-wrapper">
                <?= Html::submitBtn('save', ['class' => 'btn btn-block text-center btn-primary'], 'emsl'); ?>
            </div>
            <?= Html::formEnd(); ?>

        </div>
    </main>

    <div class="toasts">
        <?= Notification::toast('fail_insecure', 'toast fadeOut toast-invalid'); ?>
        <?= Notification::toast('fail_missmatch', 'toast fadeOut toast-invalid'); ?>
        <?= Notification::toast('fail_old_password', 'toast fadeOut toast-invalid') ?>
        <?= Notification::toast('success', 'toast fadeOut toast-success') ?>
    </div>
</zone:body>