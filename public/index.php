<?php
require __DIR__ . '/../continy/__bootstrap.php';

use App\Models\EMSLConfig;
use Continy\Core\App;
use Continy\Core\Config;

$app = new App;
$app->setTimezone(EMSLConfig::get('timezone'));
$app->run();