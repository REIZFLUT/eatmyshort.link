<?php

use Continy\Core\Notification;
use Continy\Core\Html;
use App\Models\EMSLConfig;
?>

<zone:body>
  <main id="login" style="margin-top: 80px;">
    <div class="center-360 py-20">
      <form method="POST">
        <?= Html::csrf(); ?>
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
      <?= Notification::toast('loginFail'); ?>

    </div>
  </main>

</zone:body>