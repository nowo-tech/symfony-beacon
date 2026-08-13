<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Indexes for new-in-release filters and event user-identifier search.
 */
final class Version20260813180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add idx_issue_project_first_release and idx_event_issue_user_identifier';
    }

    public function up(Schema $schema): void
    {
        $issue = $schema->getTable('issue');
        if (!$issue->hasIndex('idx_issue_project_first_release')) {
            $this->addSql('CREATE INDEX idx_issue_project_first_release ON issue (project_id, first_release)');
        }

        $event = $schema->getTable('event');
        if (!$event->hasIndex('idx_event_issue_user_identifier')) {
            $this->addSql('CREATE INDEX idx_event_issue_user_identifier ON event (issue_id, user_identifier)');
        }
    }

    public function down(Schema $schema): void
    {
        $issue = $schema->getTable('issue');
        if ($issue->hasIndex('idx_issue_project_first_release')) {
            $this->addSql('DROP INDEX idx_issue_project_first_release ON issue');
        }

        $event = $schema->getTable('event');
        if ($event->hasIndex('idx_event_issue_user_identifier')) {
            $this->addSql('DROP INDEX idx_event_issue_user_identifier ON event');
        }
    }
}
