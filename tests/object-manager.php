<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

$loader = require __DIR__.'/../vendor/autoload.php';

// Same worktree caveat as tests/bootstrap.php: the shared vendor symlink resolves App\
// to the main checkout, so point the loader at this tree before the kernel is built.
if ($loader instanceof Composer\Autoload\ClassLoader) {
    $loader->setPsr4('App\\', [dirname(__DIR__).'/src']);
}

new Dotenv()->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
