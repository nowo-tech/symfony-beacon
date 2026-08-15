<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index issue(project_id, last_environment) for list filters and env compare.
 */
final class Version20260815231000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add idx_issue_project_last_environment for environment list filters';
    }

    public function up(Schema $schema): void
    {
        $issue = $schema->getTable('issue');
        if (!$issue->hasIndex('idx_issue_project_last_environment')) {
            $this->addSql('CREATE INDEX idx_issue_project_last_environment ON issue (project_id, last_environment)');
        }
    }

    public function down(Schema $schema): void
    {
        $issue = $schema->getTable('issue');
        if ($issue->hasIndex('idx_issue_project_last_environment')) {
            $this->addSql('DROP INDEX idx_issue_project_last_environment ON issue');
        }
    }
}
