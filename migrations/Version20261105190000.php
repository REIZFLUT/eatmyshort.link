<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hand written: doctrine:migrations:diff would recreate the whole schema because
 * the database of a fresh checkout is empty.
 */
final class Version20261105190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a per-user salt for the hashed TOTP backup codes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD COLUMN backup_codes_salt VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP COLUMN backup_codes_salt');
    }
}
