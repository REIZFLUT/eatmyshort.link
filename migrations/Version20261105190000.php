<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the per-user salt for the hashed TOTP backup codes. Described through the
 * schema API so the added column is emitted with the platform's own DDL.
 */
final class Version20261105190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a per-user salt for the hashed TOTP backup codes';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('app_user')
            ->addColumn('backup_codes_salt', Types::STRING, ['length' => 32, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('app_user')->dropColumn('backup_codes_salt');
    }
}
