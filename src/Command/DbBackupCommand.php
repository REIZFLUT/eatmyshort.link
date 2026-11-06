<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Writes a consistent copy of the database while the app keeps running.
 *
 * SQLite is copied in-process with VACUUM INTO. MySQL/MariaDB is dumped with the client
 * tools (--single-transaction), so a running app is not blocked and the dump is consistent
 * for InnoDB tables. Credentials are handed to the tool through MYSQL_PWD, never on the
 * command line, so they stay out of the process list.
 */
#[AsCommand(name: 'app:db:backup', description: 'Writes a consistent database backup: VACUUM INTO for SQLite, mariadb-dump/mysqldump for MySQL/MariaDB')]
final readonly class DbBackupCommand
{
    /** Client tools usable for MySQL/MariaDB; the MariaDB one is preferred when both exist. */
    public const array DUMP_TOOLS = ['mariadb-dump', 'mysqldump'];

    public function __construct(
        private Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Target file (default: var/backup/eatmyshort-<timestamp>.sqlite for SQLite, var/backup/eatmyshort-<timestamp>.sql.gz for MySQL/MariaDB)')] ?string $target = null): int
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            return $this->backupSqlite($io, $target);
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            return $this->backupMysql($io, $target);
        }

        $io->error('Only SQLite and MySQL/MariaDB databases can be backed up with this command; use the tools of your database for other platforms.');

        return Command::FAILURE;
    }

    private function backupSqlite(SymfonyStyle $io, ?string $target): int
    {
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

        // The copy contains password hashes, TOTP secrets and API tokens: owner only.
        clearstatcache(true, $target);
        if (!chmod($target, 0o600) || 0o600 !== (fileperms($target) & 0o777)) {
            $io->error(\sprintf('Cannot restrict the permissions of %s to 0600.', $target));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Backup written to %s (%s KB).', $target, number_format((int) filesize($target) / 1024, 0, ',', '.')));

        return Command::SUCCESS;
    }

    private function backupMysql(SymfonyStyle $io, ?string $target): int
    {
        $tool = $this->findDumpTool();
        if (null === $tool) {
            $io->error(\sprintf(
                'Neither "%s" was found in PATH: install the MariaDB or MySQL client tools (e.g. the "mariadb-client" or "mysql-client" package) and run this command again.',
                implode('" nor "', self::DUMP_TOOLS),
            ));

            return Command::FAILURE;
        }

        $target ??= \sprintf('%s/var/backup/eatmyshort-%s.sql.gz', $this->projectDir, date('Ymd-His'));
        if (file_exists($target)) {
            $io->error("$target already exists.");

            return Command::FAILURE;
        }

        $dir = \dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0o770, true) && !is_dir($dir)) {
            $io->error("Cannot create $dir.");

            return Command::FAILURE;
        }

        // Dump to a plain .sql file first and compress afterwards: the client tools write
        // gzip themselves only through version-specific flags, a stream keeps it simple.
        $gzip = str_ends_with(strtolower($target), '.gz');
        $dumpTarget = $gzip ? substr($target, 0, -3) : $target;
        if ('' === $dumpTarget) {
            $dumpTarget = $target.'.sql';
        }

        $params = $this->connection->getParams();
        $database = $params['dbname'] ?? null;
        if (!\is_string($database) || '' === $database) {
            $io->error('The database name could not be determined from DATABASE_URL.');

            return Command::FAILURE;
        }

        $process = new Process(array_merge([$tool], $this->dumpArguments($params, $database, $dumpTarget)));
        // The password goes through the environment, not the argument list, so it does not
        // show up in the process list (ps) or the shell history.
        $process->setEnv(['MYSQL_PWD' => (string) ($params['password'] ?? '')]);
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            if (is_file($dumpTarget)) {
                unlink($dumpTarget);
            }
            $io->error(\sprintf(
                'The database dump failed: %s',
                trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'unknown error',
            ));

            return Command::FAILURE;
        }

        if ($gzip) {
            if (!$this->gzip($dumpTarget, $target)) {
                unlink($dumpTarget);
                $io->error(\sprintf('Cannot compress the dump to %s.', $target));

                return Command::FAILURE;
            }
            unlink($dumpTarget);
        }

        // The dump contains password hashes, TOTP secrets and API tokens: owner only.
        clearstatcache(true, $target);
        if (!chmod($target, 0o600) || 0o600 !== (fileperms($target) & 0o777)) {
            $io->error(\sprintf('Cannot restrict the permissions of %s to 0600.', $target));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Backup written to %s (%s KB).', $target, number_format((int) filesize($target) / 1024, 0, ',', '.')));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<string>
     */
    private function dumpArguments(array $params, string $database, string $dumpTarget): array
    {
        $arguments = [
            '--single-transaction',
            '--quick',
            '--default-character-set='.(\is_string($params['charset'] ?? null) ? $params['charset'] : 'utf8mb4'),
            '--result-file='.$dumpTarget,
        ];

        $socket = $params['unix_socket'] ?? null;
        if (\is_string($socket) && '' !== $socket) {
            // A Unix socket replaces host and port.
            $arguments[] = '--socket='.$socket;
        } else {
            $arguments[] = '--host='.(\is_string($params['host'] ?? null) ? $params['host'] : '127.0.0.1');
            if (isset($params['port']) && '' !== (string) $params['port']) {
                $arguments[] = '--port='.(string) $params['port'];
            }
        }

        $user = $params['user'] ?? null;
        if (\is_string($user) && '' !== $user) {
            $arguments[] = '--user='.$user;
        }

        $arguments[] = $database;

        return $arguments;
    }

    private function findDumpTool(): ?string
    {
        $finder = new ExecutableFinder();
        foreach (self::DUMP_TOOLS as $tool) {
            $path = $finder->find($tool);
            if (null !== $path) {
                return $path;
            }
        }

        return null;
    }

    private function gzip(string $source, string $target): bool
    {
        $in = fopen($source, 'rb');
        if (false === $in) {
            return false;
        }

        $out = gzopen($target, 'wb9');
        if (false === $out) {
            fclose($in);

            return false;
        }

        $ok = true;
        while (!feof($in)) {
            $chunk = fread($in, 1 << 20);
            if (false === $chunk) {
                $ok = false;
                break;
            }
            if (false === gzwrite($out, $chunk)) {
                $ok = false;
                break;
            }
        }

        fclose($in);
        if (!gzclose($out)) {
            $ok = false;
        }

        return $ok;
    }
}
