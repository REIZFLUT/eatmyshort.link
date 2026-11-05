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
 * Other drivers pass through untouched.
 */
#[AsMiddleware]
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

                if (\in_array($params['driver'] ?? null, ['pdo_sqlite', 'sqlite3'], true)) {
                    foreach (SqlitePragmaMiddleware::PRAGMAS as $pragma => $value) {
                        $connection->exec(\sprintf('PRAGMA %s = %s', $pragma, $value));
                    }
                }

                return $connection;
            }
        };
    }
}
