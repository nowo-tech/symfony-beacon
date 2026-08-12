<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Member alert preferences (091): master flag + relational event tables (no JSON).
 */
final class Version20260812140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add member_alerts_enabled, member_project_alert_preference, member_account_alert_event, member_project_alert_event';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD member_alerts_enabled TINYINT(1) DEFAULT 1 NOT NULL');

        $this->addSql('CREATE TABLE member_project_alert_preference (id INT AUTO_INCREMENT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, project_id INT NOT NULL, INDEX idx_member_project_alert_user (user_id), UNIQUE INDEX uniq_member_project_alert_user_project (user_id, project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE member_project_alert_preference ADD CONSTRAINT FK_MPA_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE member_project_alert_preference ADD CONSTRAINT FK_MPA_PROJECT FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE member_account_alert_event (id INT AUTO_INCREMENT NOT NULL, event VARCHAR(32) NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, scope VARCHAR(16) DEFAULT \'all\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX idx_member_account_alert_user (user_id), UNIQUE INDEX uniq_member_account_alert_user_event (user_id, event), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE member_account_alert_event ADD CONSTRAINT FK_MAAE_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE member_project_alert_event (id INT AUTO_INCREMENT NOT NULL, event VARCHAR(32) NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, scope VARCHAR(16) DEFAULT \'all\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, project_id INT NOT NULL, INDEX idx_member_project_alert_event_user (user_id), INDEX idx_member_project_alert_event_project (project_id), UNIQUE INDEX uniq_member_project_alert_event (user_id, project_id, event), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE member_project_alert_event ADD CONSTRAINT FK_MPAE_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE member_project_alert_event ADD CONSTRAINT FK_MPAE_PROJECT FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_project_alert_event DROP FOREIGN KEY FK_MPAE_USER');
        $this->addSql('ALTER TABLE member_project_alert_event DROP FOREIGN KEY FK_MPAE_PROJECT');
        $this->addSql('DROP TABLE member_project_alert_event');
        $this->addSql('ALTER TABLE member_account_alert_event DROP FOREIGN KEY FK_MAAE_USER');
        $this->addSql('DROP TABLE member_account_alert_event');
        $this->addSql('ALTER TABLE member_project_alert_preference DROP FOREIGN KEY FK_MPA_USER');
        $this->addSql('ALTER TABLE member_project_alert_preference DROP FOREIGN KEY FK_MPA_PROJECT');
        $this->addSql('DROP TABLE member_project_alert_preference');
        $this->addSql('ALTER TABLE app_user DROP member_alerts_enabled');
    }
}
