<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification_destination table for project outbound alerts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_destination (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, label VARCHAR(120) NOT NULL, type VARCHAR(20) NOT NULL, endpoint_url VARCHAR(2048) NOT NULL, enabled TINYINT(1) NOT NULL, categories JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_notif_dest_project_enabled (project_id, enabled), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE notification_destination ADD CONSTRAINT FK_NOTIF_DEST_PROJECT FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_destination DROP FOREIGN KEY FK_NOTIF_DEST_PROJECT');
        $this->addSql('DROP TABLE notification_destination');
    }
}
