<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

$projectDir = dirname(__DIR__);

$loader = require $projectDir.'/vendor/autoload.php';

// In einem Git-Worktree ist vendor/ ein Symlink auf den Haupt-Checkout; Composer wuerde
// App\ (und damit kernel.project_dir, config/, templates/ und migrations/) dort aufloesen.
// Die PSR-4-Praefixe auf diesen Baum zurueckbiegen, damit Tests und Migration den Code
// dieses Worktrees nutzen. Eine normale Installation bleibt unveraendert.
$isWorktree = is_link($projectDir.'/vendor');
if ($isWorktree && $loader instanceof ClassLoader) {
    $loader->setPsr4('App\\', [$projectDir.'/src']);
    $loader->setPsr4('App\\Tests\\', [$projectDir.'/tests']);
}

// Testwerte ohne .env im Repository. putenv() ist noetig, damit der weiter unten
// gestartete Unterprozess (php bin/console doctrine:migrations:migrate --env=test)
// dieselben Werte sieht; $_SERVER/$_ENV decken den laufenden Prozess ab.
$testEnv = [
    'KERNEL_CLASS' => 'App\Kernel',
    'APP_ENV' => 'test',
    'APP_SECRET' => '$ecretf0rt3st',
    // Im Worktree absoluter Pfad: der Unterprozess loest %kernel.project_dir% sonst im
    // Haupt-Checkout auf und migriert die falsche Datei.
    'DATABASE_URL' => $isWorktree
        ? 'sqlite:///'.$projectDir.'/var/data/test.sqlite'
        : 'sqlite:///%kernel.project_dir%/var/data/test.sqlite',
    'DEFAULT_URI' => 'http://localhost',
    'APP_ADMIN_HOSTS' => 'admin.emsl.test',
    'ADMIN_IP_ALLOWLIST' => '',
    'MAILER_DSN' => 'null://null',
    'MAILER_FROM' => 'EatMyShort.Link <noreply@localhost>',
];

foreach ($testEnv as $name => $value) {
    putenv($name.'='.$value);
    $_SERVER[$name] = $_ENV[$name] = $value;
}

// Fresh SQLite test database built from the real migrations (DAMA rolls back every test).
if ('test' === getenv('APP_ENV')) {
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
