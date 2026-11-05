<?php

declare(strict_types=1);

/*
 * In a git worktree vendor/ is a symlink into the main checkout, so Composer resolves
 * App\ (and with it kernel.project_dir, config/, templates/ and migrations/) to that
 * checkout. Pinning the PSR-4 prefixes back to this tree keeps the tests and the
 * migration run on the code of the worktree they are started from.
 */
use Composer\Autoload\ClassLoader;

$loader = require dirname(__DIR__).'/vendor/autoload.php';

if ($loader instanceof ClassLoader) {
    $loader->setPsr4('App\\', [dirname(__DIR__).'/src']);
    $loader->setPsr4('App\\Tests\\', [__DIR__]);
}

return $loader;
