<?php

use App\Models\EMSLConfig;
use Continy\Core\Html;
use App\Models\User;
use Continy\Core\Config;
?>


<zone:navi>
    <nav class="top-navi">
        <figure class="mainMenuLogoContainer">
            <a href="<?= Config::get('app/url') ?>/shortlink">
                <img src="<?= EMSLConfig::get('topLogo'); ?>" id="mainMenuLogo" alt="">
            </a>
        </figure>
        <div class="menu">
            <a href="#mainMenu" id="mainMenuBar" onclick="javascript:return false;">
                <img src="<?= EMSLConfig::get('menuBarIcon') ?>" alt="" id="mainMenuIcon">
            </a>
            <div id="mainMenuContent">
                <ul>
                    <li class="mainMenuItem"><?= Html::routeLink('/shortlink', 'Shortlinks') ?></li>
                    <li class="mainMenuItem"><?= Html::routeLink('/user/account', 'Mein Account') ?></li>
                    <?php if (User::hasRole('manager')) : ?>
                        <li class="mainMenuItem"><?= Html::routeLink('/setting', 'Einstellungen') ?></li>
                        <li class="mainMenuItem"><?= Html::routeLink('/user', 'Benutzerverwaltung') ?></li>
                    <?php endif; ?>
                    <li class="mainMenuItem"><?= Html::routeLink('/logout', 'Logout') ?></li>
                </ul>
                <?= Html::languageSwitch('/language/{lang}'); ?>
            </div>
        </div>
    </nav>
</zone:navi>