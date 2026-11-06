<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema, described through Doctrine's schema API instead of hand-written
 * SQLite DDL, so the same migration run produces valid DDL on every platform the
 * application supports (SQLite for development, MySQL/MariaDB for production).
 */
final class Version20261105184039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: users, domains, links, localisations, clicks, admin IP rules, API tokens, passkeys, settings';
    }

    /**
     * SQLite ignores "PRAGMA foreign_keys" inside a transaction, yet the engine
     * validates foreign keys while dropping tables - and DBAL emits the DROP TABLE
     * statements in schema (i.e. name) order, not in dependency order. MySQL refuses
     * to drop a table that another table still references. Both need the reject of
     * foreign keys around down(); on SQLite that only works without a transaction.
     */
    public function isTransactional(): bool
    {
        return !$this->platform instanceof SQLitePlatform;
    }

    public function up(Schema $schema): void
    {
        $user = $schema->createTable('app_user');
        $user->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $user->addColumn('email', Types::STRING, ['length' => 180]);
        $user->addColumn('display_name', Types::STRING, ['length' => 120]);
        $user->addColumn('roles', Types::JSON);
        $user->addColumn('password', Types::STRING, ['length' => 255, 'notnull' => false]);
        $user->addColumn('active', Types::BOOLEAN);
        $user->addColumn('locale', Types::STRING, ['length' => 5, 'notnull' => false]);
        $user->addColumn('webauthn_handle', Types::STRING, ['length' => 64]);
        $user->addColumn('totp_secret_encrypted', Types::TEXT, ['notnull' => false]);
        $user->addColumn('totp_enabled', Types::BOOLEAN);
        $user->addColumn('backup_codes', Types::JSON);
        $user->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $user->addColumn('last_login_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $user->setPrimaryKey(['id']);
        $user->addUniqueIndex(['email'], 'UNIQ_88BDF3E9E7927C74');
        $user->addUniqueIndex(['webauthn_handle'], 'UNIQ_88BDF3E9387A2A25');

        $domain = $schema->createTable('domain');
        $domain->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $domain->addColumn('hostname', Types::STRING, ['length' => 253]);
        $domain->addColumn('active', Types::BOOLEAN);
        $domain->addColumn('is_default', Types::BOOLEAN);
        $domain->addColumn('root_redirect_url', Types::STRING, ['length' => 2048, 'notnull' => false]);
        $domain->addColumn('not_found_url', Types::STRING, ['length' => 2048, 'notnull' => false]);
        $domain->addColumn('forward_unknown_paths', Types::BOOLEAN);
        $domain->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $domain->setPrimaryKey(['id']);
        $domain->addUniqueIndex(['hostname'], 'UNIQ_A7A91E0BE551C011');

        $adminIpRule = $schema->createTable('admin_ip_rule');
        $adminIpRule->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $adminIpRule->addColumn('cidr', Types::STRING, ['length' => 64]);
        $adminIpRule->addColumn('label', Types::STRING, ['length' => 120, 'notnull' => false]);
        $adminIpRule->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $adminIpRule->setPrimaryKey(['id']);
        $adminIpRule->addUniqueIndex(['cidr'], 'UNIQ_918372CAF04BC9ED');

        $setting = $schema->createTable('app_setting');
        $setting->addColumn('setting_key', Types::STRING, ['length' => 64]);
        $setting->addColumn('setting_value', Types::TEXT, ['notnull' => false]);
        $setting->setPrimaryKey(['setting_key']);

        $apiToken = $schema->createTable('api_token');
        $apiToken->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $apiToken->addColumn('name', Types::STRING, ['length' => 100]);
        $apiToken->addColumn('token_hash', Types::STRING, ['length' => 64]);
        $apiToken->addColumn('hint', Types::STRING, ['length' => 16]);
        $apiToken->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $apiToken->addColumn('last_used_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $apiToken->addColumn('expires_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $apiToken->addColumn('user_id', Types::INTEGER);
        $apiToken->setPrimaryKey(['id']);
        $apiToken->addUniqueIndex(['token_hash'], 'UNIQ_7BA2F5EBB3BC57DA');
        $apiToken->addIndex(['user_id'], 'IDX_7BA2F5EBA76ED395');
        $apiToken->addForeignKeyConstraint('app_user', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_7BA2F5EBA76ED395');

        $resetToken = $schema->createTable('reset_token');
        $resetToken->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $resetToken->addColumn('selector', Types::STRING, ['length' => 24]);
        $resetToken->addColumn('verifier_hash', Types::STRING, ['length' => 64]);
        $resetToken->addColumn('purpose', Types::STRING, ['length' => 10]);
        $resetToken->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
        $resetToken->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $resetToken->addColumn('user_id', Types::INTEGER);
        $resetToken->setPrimaryKey(['id']);
        $resetToken->addUniqueIndex(['selector'], 'UNIQ_D7C8DC199692E25D');
        $resetToken->addIndex(['user_id'], 'IDX_D7C8DC19A76ED395');
        $resetToken->addForeignKeyConstraint('app_user', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_D7C8DC19A76ED395');

        $link = $schema->createTable('link');
        $link->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        // MySQL/MariaDB compare text case-insensitively unless told otherwise, SQLite does not.
        // App\Doctrine\BinaryCollationListener pins this column to a binary collation on
        // MySQL-family platforms so a lookup resolves the same rows on both drivers; the
        // migration has to produce exactly that, or doctrine:schema:validate reports drift.
        $code = $link->addColumn('code', Types::STRING, ['length' => 64]);
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $code->setPlatformOption('collation', 'utf8mb4_bin');
        }
        $link->addColumn('target_url', Types::TEXT);
        $link->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => false]);
        $link->addColumn('redirect_type', Types::SMALLINT);
        $link->addColumn('active', Types::BOOLEAN);
        $link->addColumn('expires_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $link->addColumn('click_count', Types::INTEGER);
        $link->addColumn('last_click_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $link->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $link->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $link->addColumn('domain_id', Types::INTEGER);
        $link->addColumn('owner_id', Types::INTEGER);
        $link->setPrimaryKey(['id']);
        $link->addIndex(['owner_id'], 'idx_link_owner');
        $link->addUniqueIndex(['domain_id', 'code'], 'uniq_link_domain_code');
        $link->addIndex(['domain_id'], 'IDX_36AC99F1115F0EE5');
        $link->addForeignKeyConstraint('domain', ['domain_id'], ['id'], [], 'FK_36AC99F1115F0EE5');
        $link->addForeignKeyConstraint('app_user', ['owner_id'], ['id'], [], 'FK_36AC99F17E3C61F9');

        $localisation = $schema->createTable('link_localisation');
        $localisation->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $localisation->addColumn('language', Types::STRING, ['length' => 12]);
        $localisation->addColumn('target_url', Types::TEXT);
        $localisation->addColumn('link_id', Types::INTEGER);
        $localisation->setPrimaryKey(['id']);
        $localisation->addUniqueIndex(['link_id', 'language'], 'uniq_localisation_link_language');
        $localisation->addIndex(['link_id'], 'IDX_4D7C4E4DADA40271');
        $localisation->addForeignKeyConstraint('link', ['link_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_4D7C4E4DADA40271');

        $click = $schema->createTable('click');
        $click->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $click->addColumn('clicked_at', Types::DATETIME_IMMUTABLE);
        $click->addColumn('clicked_day', Types::DATE_IMMUTABLE);
        $click->addColumn('visitor_hash', Types::STRING, ['length' => 64]);
        $click->addColumn('ip_prefix', Types::STRING, ['length' => 45, 'notnull' => false]);
        $click->addColumn('country_code', Types::STRING, ['length' => 2, 'notnull' => false]);
        $click->addColumn('referrer_host', Types::STRING, ['length' => 255, 'notnull' => false]);
        $click->addColumn('device_type', Types::STRING, ['length' => 16]);
        $click->addColumn('browser', Types::STRING, ['length' => 64, 'notnull' => false]);
        $click->addColumn('os', Types::STRING, ['length' => 64, 'notnull' => false]);
        $click->addColumn('language', Types::STRING, ['length' => 12, 'notnull' => false]);
        $click->addColumn('bot', Types::BOOLEAN);
        $click->addColumn('link_id', Types::INTEGER);
        $click->setPrimaryKey(['id']);
        $click->addIndex(['link_id', 'clicked_day'], 'idx_click_link_day');
        $click->addIndex(['clicked_day'], 'idx_click_day');
        $click->addIndex(['link_id'], 'IDX_BAF6C220ADA40271');
        $click->addForeignKeyConstraint('link', ['link_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_BAF6C220ADA40271');

        // WebAuthn fields come from the mapped superclass Webauthn\CredentialRecord
        // (XML mapping shipped with web-auth/webauthn-symfony-bundle).
        $credential = $schema->createTable('webauthn_credential');
        $credential->addColumn('public_key_credential_id', 'base64');
        $credential->addColumn('type', Types::STRING, ['length' => 255]);
        $credential->addColumn('transports', Types::JSON);
        $credential->addColumn('attestation_type', Types::STRING, ['length' => 255]);
        $credential->addColumn('trust_path', 'trust_path');
        $credential->addColumn('aaguid', 'aaguid', ['length' => 36]);
        $credential->addColumn('credential_public_key', 'base64');
        $credential->addColumn('user_handle', Types::STRING, ['length' => 255]);
        $credential->addColumn('counter', Types::INTEGER);
        $credential->addColumn('other_ui', Types::JSON, ['notnull' => false]);
        $credential->addColumn('backup_eligible', Types::BOOLEAN, ['notnull' => false]);
        $credential->addColumn('backup_status', Types::BOOLEAN, ['notnull' => false]);
        $credential->addColumn('uv_initialized', Types::BOOLEAN, ['notnull' => false]);
        $credential->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $credential->addColumn('name', Types::STRING, ['length' => 100]);
        $credential->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $credential->addColumn('last_used_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $credential->addColumn('user_id', Types::INTEGER);
        $credential->setPrimaryKey(['id']);
        $credential->addIndex(['user_id'], 'IDX_850123F9A76ED395');
        $credential->addForeignKeyConstraint('app_user', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_850123F9A76ED395');

        $userDomain = $schema->createTable('user_domain');
        $userDomain->addColumn('user_id', Types::INTEGER);
        $userDomain->addColumn('domain_id', Types::INTEGER);
        $userDomain->setPrimaryKey(['user_id', 'domain_id']);
        $userDomain->addIndex(['user_id'], 'IDX_C2FB0536A76ED395');
        $userDomain->addIndex(['domain_id'], 'IDX_C2FB0536115F0EE5');
        $userDomain->addForeignKeyConstraint('app_user', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_C2FB0536A76ED395');
        $userDomain->addForeignKeyConstraint('domain', ['domain_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_C2FB0536115F0EE5');
    }

    public function down(Schema $schema): void
    {
        $this->toggleForeignKeyChecks(false);

        // Reverse creation order; the exact order is irrelevant now that foreign
        // keys are not enforced, but it keeps the migration readable.
        $schema->dropTable('webauthn_credential');
        $schema->dropTable('click');
        $schema->dropTable('link_localisation');
        $schema->dropTable('link');
        $schema->dropTable('reset_token');
        $schema->dropTable('api_token');
        $schema->dropTable('user_domain');
        $schema->dropTable('app_setting');
        $schema->dropTable('admin_ip_rule');
        $schema->dropTable('domain');
        $schema->dropTable('app_user');
    }

    public function postDown(Schema $schema): void
    {
        $this->toggleForeignKeyChecks(true);
    }

    private function toggleForeignKeyChecks(bool $enabled): void
    {
        if ($this->platform instanceof SQLitePlatform) {
            $this->connection->executeStatement('PRAGMA foreign_keys = '.($enabled ? 'ON' : 'OFF'));
        } elseif ($this->platform instanceof AbstractMySQLPlatform) {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = '.($enabled ? '1' : '0'));
        }
    }
}
