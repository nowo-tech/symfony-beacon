<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Assign issues to a project member (who owns / resolves the issue).
 */
final class Version20260720214500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable issue.assignee_id FK to app_user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE issue ADD assignee_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE issue ADD CONSTRAINT FK_ISSUE_ASSIGNEE FOREIGN KEY (assignee_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_issue_project_assignee ON issue (project_id, assignee_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE issue DROP FOREIGN KEY FK_ISSUE_ASSIGNEE');
        $this->addSql('DROP INDEX idx_issue_project_assignee ON issue');
        $this->addSql('ALTER TABLE issue DROP assignee_id');
    }
}
