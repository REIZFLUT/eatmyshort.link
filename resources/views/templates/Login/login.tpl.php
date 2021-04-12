<?php

use Continy\Core\Notification;
use Continy\Core\Html;
use App\Models\EMSLConfig;
use Continy\Core\Language;

?>

<zone:body>
  <main id="login" style="margin-top: 80px;">
    <div class="center-360 py-20">
      <?= Html::formPostStart('/login'); ?>
      <div class="mb-20">
        <img src="<?= EMSLConfig::get('topLogo'); ?>" id="mainMenuLogo" alt="">
      </div>
      <div class="input-wrapper">
        <?= Html::input('email', 'email', '', ['required' => 'required'], 'login'); ?>
        <?= Notification::get('email'); ?>
      </div>
      <div class="input-wrapper">
        <?= Html::input('password', 'password', '', ['required' => 'required'], 'login'); ?>
        <?= Notification::get('password'); ?>
      </div>
      <div class="input-wrapper">
        <?= Html::submitBtn('submit', ['class' => 'btn btn-block text-center btn-primary'], 'login'); ?>
      </div>
      </form>
    </div>
    <div class="center-360">
      <?= Html::routeLink('/login/forgot', Language::get('login/link/forgotPwd')); ?>
    </div>
  </main>
  <div class="toasts">
  <?= Notification::toast('success', 'toast fadeOut toast-success'); ?>
    <?= Notification::toast('loginFail', 'toast fadeOut toast-invalid'); ?>
  </div>

</zone:body>