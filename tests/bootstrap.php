<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

$loader = require dirname(__DIR__).'/vendor/autoload.php';

// vendor/ is a symlink to the main checkout in a git worktree; Composer then resolves
// App\ to that checkout. Point the loader back here so the tests exercise this tree.
if ($loader instanceof Composer\Autoload\ClassLoader) {
    $loader->setPsr4('App\\', [dirname(__DIR__).'/src']);
    $loader->setPsr4('App\\Tests\\', [__DIR__]);
}

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

// Fresh SQLite test database built from the real migrations (DAMA rolls back every test).
if ('test' === $_SERVER['APP_ENV']) {
    $database = dirname(__DIR__).'/var/data/test.sqlite';
    foreach ([$database, $database.'-wal', $database.'-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    passthru(sprintf(
        'DATABASE_URL=%s %s %s/bin/console doctrine:migrations:migrate --no-interaction --quiet --env=test',
        escapeshellarg('sqlite:///'.$database),
        escapeshellarg(PHP_BINARY),
        escapeshellarg(dirname(__DIR__)),
    ), $exitCode);
    if (0 !== $exitCode) {
        exit($exitCode);
    }
}
