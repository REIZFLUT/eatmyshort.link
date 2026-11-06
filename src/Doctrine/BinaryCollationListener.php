<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Link;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

/**
 * Keeps the comparison semantics of exact-match columns identical on every driver.
 *
 * SQLite compares TEXT byte by byte (case-sensitive), while MySQL/MariaDB use the table's
 * default collation, which is case-insensitive (utf8mb4_*_ci) unless stated otherwise. A
 * column that is looked up by exact equality therefore behaves differently on the two
 * backends - e.g. a request for the short code "/ABC" would find the link "abc" on MariaDB
 * but not on SQLite, and a unique index would reject "abc"/"ABC" as duplicates on MariaDB
 * while SQLite accepts both.
 *
 * We pin those columns to a binary collation on MySQL/MariaDB so that the case-sensitive
 * SQLite behaviour becomes the semantics everywhere. The option is written into the column's
 * platform options ("collation"); that is the key DBAL turns into a COLLATE clause - see
 * AbstractPlatform::getColumnDeclarationSQL() together with
 * AbstractMySQLPlatform::supportsColumnCollation(). SQLite and any other driver are left
 * untouched, so their DDL (and thus the migrations that are checked with
 * doctrine:schema:validate) does not change. On SQLite the collation must *not* be set
 * either: it also reports supportsColumnCollation() === true but only knows BINARY/NOCASE/
 * RTRIM, so "utf8mb4_bin" would be rejected there.
 *
 * Which columns need this and which do not:
 *  - link.code         -> yes. Hot lookup key of every redirect and part of the unique
 *                         (domain_id, code) index. Normalised to lower case by the entity and
 *                         by the repository, but the column must still compare exactly.
 *  - domain.hostname   -> no. Domain::normalizeHostname() lower-cases and IDN-encodes before
 *                         every write *and* every lookup, so ci and bin produce the same rows.
 *  - app_user.email    -> no. User::setEmail() stores lower case and findOneByEmail()
 *                         lower-cases the argument, so the comparison already is case-insensitive
 *                         in PHP on both drivers.
 *  - api_token.token_hash -> no. sha256 hex digests are always lower case on write and lookup.
 *  - webauthn_credential.public_key_credential_id -> no. Base64 (case-sensitive) but an opaque
 *                         value that the browser always replays byte for byte, so a case
 *                         difference never reaches the database.
 */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
final class BinaryCollationListener
{
    /**
     * Binary collation for exact-match columns. utf8mb4_bin exists on both MySQL 8 and
     * MariaDB (unlike, say, MySQL 8's utf8mb4_0900_bin), so it is the portable choice.
     */
    public const string BINARY_COLLATION = 'utf8mb4_bin';

    /** @var array<class-string, array<string, string>> entity => field name => collation */
    private const array EXACT_MATCH_COLUMNS = [
        Link::class => [
            'code' => self::BINARY_COLLATION,
        ],
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        // Bail out before touching any metadata: on SQLite (and anything that is not
        // MySQL/MariaDB) the generated DDL has to stay byte for byte the same as before.
        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        $metadata = $event->getClassMetadata();
        $fields = self::EXACT_MATCH_COLUMNS[$metadata->getName()] ?? [];

        foreach ($fields as $field => $collation) {
            if (!isset($metadata->fieldMappings[$field])) {
                continue;
            }

            $mapping = $metadata->fieldMappings[$field];
            $options = $mapping->options ?? [];
            // Never override an explicit collation set in the mapping.
            $options['collation'] ??= $collation;
            $mapping->options = $options;
        }
    }
}
