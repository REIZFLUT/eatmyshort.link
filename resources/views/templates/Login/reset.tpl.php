<?php

use Continy\Core\Notification;
use Continy\Core\Html;
use App\Models\EMSLConfig;
use Continy\Core\Language;

?>

<zone:body>
    <main id="login" style="margin-top: 80px;">
        <div class="center-360 py-20">
            <?= Html::formPostStart('/login/reset'); ?>
            <input type="hidden" name="token" value="<?= $_GET['token'] ?? '' ?>">
            <div class="mb-20">
                <img src="<?= EMSLConfig::get('topLogo'); ?>" id="mainMenuLogo" alt="">
            </div>
            <div class="input-wrapper">
                <?= Html::input('email', 'email', $_GET['email'] ?? '', ['required' => 'required'], 'login'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::input('password', 'password_new', '', ['required' => 'required'], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::input('password', 'password_cfm', '', ['required' => 'required'], 'emsl'); ?>
            </div>
            <div class="input-wrapper">
                <?= Html::submitBtn('resetPwd', ['class' => 'btn btn-block text-center btn-primary'], 'login'); ?>
            </div>
            </form>
        </div>
        <div class="center-360">
            <?= Html::routeLink('/login', Language::get('login/link/back')); ?>
        </div>
    </main>
    <div class="toasts">
        <?= Notification::toast('email', 'toast fadeOut toast-invalid'); ?>
        <?= Notification::toast('password_new', 'toast fadeOut toast-invalid'); ?>
        <?= Notification::toast('fail', 'toast fadeOut toast-invalid'); ?>
        <?= Notification::toast('success', 'toast fadeOut toast-success'); ?>
    </div>
</zone:body>