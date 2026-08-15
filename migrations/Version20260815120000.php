<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Promote event tags + request URL for indexed issue filters (no payload JSON_SEARCH).
 */
final class Version20260815120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event.request_url and event_tag table for promoted Envelope filters';
    }

    public function up(Schema $schema): void
    {
        $event = $schema->getTable('event');
        if (!$event->hasColumn('request_url')) {
            $this->addSql('ALTER TABLE event ADD request_url VARCHAR(512) DEFAULT NULL');
        }
        if (!$event->hasIndex('idx_event_project_request_url')) {
            $this->addSql('CREATE INDEX idx_event_project_request_url ON event (project_id, request_url)');
        }

        if (!$schema->hasTable('event_tag')) {
            $this->addSql('CREATE TABLE event_tag (
                id INT AUTO_INCREMENT NOT NULL,
                event_id INT NOT NULL,
                issue_id INT NOT NULL,
                project_id INT NOT NULL,
                tag_key VARCHAR(120) NOT NULL,
                tag_value VARCHAR(255) NOT NULL,
                INDEX idx_event_tag_project_key (project_id, tag_key),
                INDEX idx_event_tag_project_value (project_id, tag_value),
                INDEX idx_event_tag_issue (issue_id),
                UNIQUE INDEX uniq_event_tag_event_key (event_id, tag_key),
                PRIMARY KEY(id),
                CONSTRAINT FK_event_tag_event FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE,
                CONSTRAINT FK_event_tag_issue FOREIGN KEY (issue_id) REFERENCES issue (id) ON DELETE CASCADE,
                CONSTRAINT FK_event_tag_project FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('event_tag')) {
            $this->addSql('DROP TABLE event_tag');
        }

        $event = $schema->getTable('event');
        if ($event->hasIndex('idx_event_project_request_url')) {
            $this->addSql('DROP INDEX idx_event_project_request_url ON event');
        }
        if ($event->hasColumn('request_url')) {
            $this->addSql('ALTER TABLE event DROP request_url');
        }
    }
}
