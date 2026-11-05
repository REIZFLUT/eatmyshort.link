<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Consistent copy of the SQLite database while the app keeps running (VACUUM INTO).
 */
#[AsCommand(name: 'app:db:backup', description: 'Writes a consistent copy of the SQLite database')]
final readonly class DbBackupCommand
{
    public function __construct(
        private Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Target file (default: var/backup/eatmyshort-<timestamp>.sqlite)')] ?string $target = null): int
    {
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $io->error('Only SQLite databases can be backed up with this command; use your database tools.');

            return Command::FAILURE;
        }

        $target ??= \sprintf('%s/var/backup/eatmyshort-%s.sqlite', $this->projectDir, date('Ymd-His'));
        if (file_exists($target)) {
            $io->error("$target already exists.");

            return Command::FAILURE;
        }
        $dir = \dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0o770, true) && !is_dir($dir)) {
            $io->error("Cannot create $dir.");

            return Command::FAILURE;
        }

        $source = $this->connection->getParams()['path'] ?? null;
        if (!\is_string($source) || !is_file($source)) {
            $io->error('The SQLite database file could not be determined.');

            return Command::FAILURE;
        }
        // Own connection: VACUUM is not allowed inside a transaction of the app connection.
        new \PDO('sqlite:'.$source)->prepare('VACUUM INTO ?')->execute([$target]);
        $io->success(\sprintf('Backup written to %s (%s KB).', $target, number_format((int) filesize($target) / 1024, 0, ',', '.')));

        return Command::SUCCESS;
    }
}
