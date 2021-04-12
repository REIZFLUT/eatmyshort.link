<?php

use Continy\Core\Config;
use App\Models\EMSLConfig;

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>

<head>
    <base href="<?= Config::get('app/url') ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="shortcut icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link href="assets/roboto-light/stylesheet.css" rel="stylesheet">
    <link href="<?= EMSLConfig::get('theme') ?>" rel="stylesheet">
    <!-- <link href="<?= Config::get('app/url') ?>/assets/css/emsl.css" rel="stylesheet"> -->
    <link href="http://mcss.rfkb.de/css_files/emsl/emsl.css" rel="stylesheet">

    <title><?= EMSLConfig::get('title') ?></title>
    <zone:head />
</head>

<body>
    <zone:navi />
    <zone:body />
    <footer>
        <div class="credits">
        Created by O.Deger, Reizflut Kreativbüro
        </div>
        <div class="licence">
        <img src="assets/img/cc-by-sa.png" alt="Licence CC BY SA" style="height:30px;">
        </div>
    </footer>
    <zone:debug />

    <script>
        // Handle Toasts
        window.setTimeout(function() {
            var toasts = document.getElementsByClassName('toast');
            for (i = 0; i < toasts.length; i++) {
                toasts[i].classList.remove('fadeOut');
            }
        }, 6000)

        // Navigation
        var menuItem = document.getElementsByClassName('mainMenuItem');
        for(i=0; i < menuItem.length; i++){
            menuItem[i].addEventListener('click', function(e){
                var link = this.children[0];
                window.location = link.href;
            })
        }

    </script>


</body>

</html>