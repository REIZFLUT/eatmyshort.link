<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261105184039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: users, domains, links, localisations, clicks, admin IP rules, API tokens, passkeys, settings';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_ip_rule (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, cidr VARCHAR(64) NOT NULL, label VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_918372CAF04BC9ED ON admin_ip_rule (cidr)');
        $this->addSql('CREATE TABLE api_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, token_hash VARCHAR(64) NOT NULL, hint VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_7BA2F5EBA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7BA2F5EBB3BC57DA ON api_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_7BA2F5EBA76ED395 ON api_token (user_id)');
        $this->addSql('CREATE TABLE app_setting (setting_key VARCHAR(64) NOT NULL, setting_value CLOB DEFAULT NULL, PRIMARY KEY (setting_key))');
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(120) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) DEFAULT NULL, active BOOLEAN NOT NULL, locale VARCHAR(5) DEFAULT NULL, webauthn_handle VARCHAR(64) NOT NULL, totp_secret_encrypted CLOB DEFAULT NULL, totp_enabled BOOLEAN NOT NULL, backup_codes CLOB NOT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9387A2A25 ON app_user (webauthn_handle)');
        $this->addSql('CREATE TABLE user_domain (user_id INTEGER NOT NULL, domain_id INTEGER NOT NULL, PRIMARY KEY (user_id, domain_id), CONSTRAINT FK_C2FB0536A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C2FB0536115F0EE5 FOREIGN KEY (domain_id) REFERENCES domain (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C2FB0536A76ED395 ON user_domain (user_id)');
        $this->addSql('CREATE INDEX IDX_C2FB0536115F0EE5 ON user_domain (domain_id)');
        $this->addSql('CREATE TABLE click (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, clicked_at DATETIME NOT NULL, clicked_day DATE NOT NULL, visitor_hash VARCHAR(64) NOT NULL, ip_prefix VARCHAR(45) DEFAULT NULL, country_code VARCHAR(2) DEFAULT NULL, referrer_host VARCHAR(255) DEFAULT NULL, device_type VARCHAR(16) NOT NULL, browser VARCHAR(64) DEFAULT NULL, os VARCHAR(64) DEFAULT NULL, language VARCHAR(12) DEFAULT NULL, bot BOOLEAN NOT NULL, link_id INTEGER NOT NULL, CONSTRAINT FK_BAF6C220ADA40271 FOREIGN KEY (link_id) REFERENCES link (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_click_link_day ON click (link_id, clicked_day)');
        $this->addSql('CREATE INDEX idx_click_day ON click (clicked_day)');
        $this->addSql('CREATE INDEX IDX_BAF6C220ADA40271 ON click (link_id)');
        $this->addSql('CREATE TABLE domain (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, hostname VARCHAR(253) NOT NULL, active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, root_redirect_url VARCHAR(2048) DEFAULT NULL, not_found_url VARCHAR(2048) DEFAULT NULL, forward_unknown_paths BOOLEAN NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A7A91E0BE551C011 ON domain (hostname)');
        $this->addSql('CREATE TABLE link (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(64) NOT NULL, target_url CLOB NOT NULL, title VARCHAR(255) DEFAULT NULL, redirect_type SMALLINT NOT NULL, active BOOLEAN NOT NULL, expires_at DATETIME DEFAULT NULL, click_count INTEGER NOT NULL, last_click_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, domain_id INTEGER NOT NULL, owner_id INTEGER NOT NULL, CONSTRAINT FK_36AC99F1115F0EE5 FOREIGN KEY (domain_id) REFERENCES domain (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_36AC99F17E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_link_owner ON link (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_link_domain_code ON link (domain_id, code)');
        $this->addSql('CREATE INDEX IDX_36AC99F1115F0EE5 ON link (domain_id)');
        $this->addSql('CREATE TABLE link_localisation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, language VARCHAR(12) NOT NULL, target_url CLOB NOT NULL, link_id INTEGER NOT NULL, CONSTRAINT FK_4D7C4E4DADA40271 FOREIGN KEY (link_id) REFERENCES link (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX uniq_localisation_link_language ON link_localisation (link_id, language)');
        $this->addSql('CREATE INDEX IDX_4D7C4E4DADA40271 ON link_localisation (link_id)');
        $this->addSql('CREATE TABLE reset_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, selector VARCHAR(24) NOT NULL, verifier_hash VARCHAR(64) NOT NULL, purpose VARCHAR(10) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_D7C8DC19A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D7C8DC199692E25D ON reset_token (selector)');
        $this->addSql('CREATE INDEX IDX_D7C8DC19A76ED395 ON reset_token (user_id)');
        $this->addSql('CREATE TABLE webauthn_credential (public_key_credential_id CLOB NOT NULL, type VARCHAR(255) NOT NULL, transports CLOB NOT NULL, attestation_type VARCHAR(255) NOT NULL, trust_path CLOB NOT NULL, aaguid CLOB NOT NULL, credential_public_key CLOB NOT NULL, user_handle VARCHAR(255) NOT NULL, counter INTEGER NOT NULL, other_ui CLOB DEFAULT NULL, backup_eligible BOOLEAN DEFAULT NULL, backup_status BOOLEAN DEFAULT NULL, uv_initialized BOOLEAN DEFAULT NULL, id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_850123F9A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_850123F9A76ED395 ON webauthn_credential (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE admin_ip_rule');
        $this->addSql('DROP TABLE api_token');
        $this->addSql('DROP TABLE app_setting');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE user_domain');
        $this->addSql('DROP TABLE click');
        $this->addSql('DROP TABLE domain');
        $this->addSql('DROP TABLE link');
        $this->addSql('DROP TABLE link_localisation');
        $this->addSql('DROP TABLE reset_token');
        $this->addSql('DROP TABLE webauthn_credential');
    }
}
