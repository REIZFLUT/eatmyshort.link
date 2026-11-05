<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Sets the SQLite pragmas the app relies on for every new connection:
 * WAL (readers never block the click writer), a busy timeout instead of
 * immediate "database is locked" errors, and enforced foreign keys.
 * Other drivers pass through untouched. High priority = innermost middleware, so the
 * pragmas reach the real connection before anything else (e.g. a test transaction) runs.
 */
#[AsMiddleware(priority: 1000)]
final class SqlitePragmaMiddleware implements Middleware
{
    public const array PRAGMAS = [
        'journal_mode' => 'WAL',
        'busy_timeout' => '5000',
        'foreign_keys' => 'ON',
        'synchronous' => 'NORMAL',
    ];

    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(array $params): Connection
            {
                $connection = parent::connect($params);

                $native = $connection->getNativeConnection();
                // A connection handed out inside an open transaction (e.g. the test suite's static
                // connection) already carries the pragmas, and SQLite refuses to change them there.
                $inTransaction = $native instanceof \PDO && $native->inTransaction();

                if (!$inTransaction && \in_array($params['driver'] ?? null, ['pdo_sqlite', 'sqlite3'], true)) {
                    foreach (SqlitePragmaMiddleware::PRAGMAS as $pragma => $value) {
                        // Switching the journal mode is the expensive one (it touches the file and
                        // can force a checkpoint). Connections from a pool usually already run in
                        // WAL, so ask first instead of paying for a no-op switch on every connect.
                        if ('journal_mode' === $pragma && strtolower($value) === strtolower((string) $connection->query('PRAGMA journal_mode')->fetchOne())) {
                            continue;
                        }
                        $connection->exec(\sprintf('PRAGMA %s = %s', $pragma, $value));
                    }
                }

                return $connection;
            }
        };
    }
}
