<?php

use Continy\Core\Notification;
use Continy\Core\Html;
use App\Models\EMSLConfig;
use Continy\Core\Language;

?>

<zone:body>
  <main id="login" style="margin-top: 80px;">
    <div class="center-360 py-20">
      <?= Html::formPostStart('/login/forgot'); ?>
      <div class="mb-20">
        <img src="<?= EMSLConfig::get('topLogo'); ?>" id="mainMenuLogo" alt="">
      </div>
      <div class="input-wrapper">
        <?= Html::input('email', 'email', '', ['required' => 'required'], 'login'); ?>
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
    <?= Notification::toast('fail', 'toast fadeOut toast-invalid'); ?>
    <?= Notification::toast('success', 'toast fadeOut toast-success'); ?>
  </div>
</zone:body>