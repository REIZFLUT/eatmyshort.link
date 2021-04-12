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
    <title><?= EMSLConfig::get('title') ?></title>
    <zone:head />
</head>

<body>
    <zone:body />
</body>

</html>