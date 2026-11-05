<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

$projectDir = dirname(__DIR__);

// No .env file is part of the repository (see .env.example), so the test configuration is
// defined here and every run behaves the same. putenv() matters: the console kernel below
// and any child process inherit the real process environment, not $_SERVER.
$testEnv = [
    'KERNEL_CLASS' => 'App\\Kernel',
    'APP_ENV' => 'test',
    'APP_SECRET' => '$ecretf0rt3st',
    'DEFAULT_URI' => 'http://localhost',
    'DATABASE_URL' => 'sqlite:///'.$projectDir.'/var/data/test.sqlite',
    'APP_ADMIN_HOSTS' => 'admin.emsl.test',
    'ADMIN_IP_ALLOWLIST' => '',
    'MAILER_DSN' => 'null://null',
    'MAILER_FROM' => 'EatMyShort.Link <noreply@localhost>',
];
foreach ($testEnv as $name => $value) {
    putenv($name.'='.$value);
    $_SERVER[$name] = $_ENV[$name] = $value;
}

require $projectDir.'/vendor/autoload.php';

// Fresh SQLite test database built from the real migrations (DAMA rolls back every test).
if ('test' === $_SERVER['APP_ENV']) {
    $database = $projectDir.'/var/data/test.sqlite';
    foreach ([$database, $database.'-wal', $database.'-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    // In-process on purpose: a subprocess could resolve another environment or project dir.
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