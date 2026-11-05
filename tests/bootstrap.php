<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

// Fresh SQLite test database built from the real migrations (DAMA rolls back every test).
if ('test' === $_SERVER['APP_ENV']) {
    $database = dirname(__DIR__).'/var/data/test.sqlite';
    foreach ([$database, $database.'-wal', $database.'-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    passthru(sprintf('%s %s/bin/console doctrine:migrations:migrate --no-interaction --quiet --env=test', escapeshellarg(PHP_BINARY), escapeshellarg(dirname(__DIR__))), $exitCode);
    if (0 !== $exitCode) {
        exit($exitCode);
    }
}
