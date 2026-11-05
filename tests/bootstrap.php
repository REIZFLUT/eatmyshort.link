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

$projectDir = dirname(__DIR__);

// Git worktrees often symlink vendor/ to the main checkout. Composer's PSR-4 map then
// points App\ at that checkout, so the tests would silently exercise the wrong code and
// the kernel would resolve %kernel.project_dir% there. Prefer this tree when vendor/ is
// a symlink; a regular installation is unaffected.
if (is_link($projectDir.'/vendor')) {
    spl_autoload_register(static function (string $class) use ($projectDir): void {
        $map = [
            'App\\Tests\\' => $projectDir.'/tests/',
            'App\\' => $projectDir.'/src/',
        ];
        foreach ($map as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = $dir.str_replace('\\', '/', substr($class, \strlen($prefix))).'.php';
                if (is_file($file)) {
                    require $file;
                }

                return;
            }
        }
    }, true, true);

    // bin/console below is a separate process and still resolves the kernel through the
    // shared vendor directory. An absolute DSN keeps both processes on the same file.
    $databaseUrl = 'sqlite:///'.$projectDir.'/var/data/test.sqlite';
    $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $databaseUrl;
    putenv('DATABASE_URL='.$databaseUrl);
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
