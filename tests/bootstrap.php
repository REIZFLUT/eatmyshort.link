<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Dotenv\Dotenv;

// Pins App\ (and with it kernel.project_dir) to this worktree, see the file's own comment.
require __DIR__.'/worktree_autoload.php';

// The test database is always this worktree's own file, whatever the shell exports.
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);
putenv('DATABASE_URL');

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

// Fresh SQLite test database built from the real migrations (DAMA rolls back every test).
if ('test' === $_SERVER['APP_ENV']) {
    $database = dirname(__DIR__).'/var/data/test.sqlite';
    foreach ([$database, $database.'-wal', $database.'-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    // In-process: bin/console would resolve the project directory of the main checkout.
    $application = new Application(new Kernel('test', true));
    $application->setAutoExit(false);
    $exitCode = $application->run(new ArrayInput([
        'command' => 'doctrine:migrations:migrate',
        '--no-interaction' => true,
        '--quiet' => true,
    ]));
    if (0 !== $exitCode) {
        exit($exitCode);
    }
}
