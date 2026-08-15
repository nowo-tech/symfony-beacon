<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index event(project_id, received_at) for ingest quota COUNT and ops windows.
 */
final class Version20260815230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add idx_event_project_received for project-scoped received_at range counts';
    }

    public function up(Schema $schema): void
    {
        $event = $schema->getTable('event');
        if (!$event->hasIndex('idx_event_project_received')) {
            $this->addSql('CREATE INDEX idx_event_project_received ON event (project_id, received_at)');
        }
    }

    public function down(Schema $schema): void
    {
        $event = $schema->getTable('event');
        if ($event->hasIndex('idx_event_project_received')) {
            $this->addSql('DROP INDEX idx_event_project_received ON event');
        }
    }
}
