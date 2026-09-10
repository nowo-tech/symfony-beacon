<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index issue(project_id, last_environment) for list filters and env compare.
 *
 * Uses live SchemaManager introspection: `$schema->hasIndex()` can miss indexes
 * left by a prior partially-committed migrate (MySQL DDL auto-commits) after a
 * web-request timeout mid-wizard.
 */
final class Version20260815231000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add idx_issue_project_last_environment for environment list filters';
    }

    public function up(Schema $schema): void
    {
        $indexes = $this->connection->createSchemaManager()->listTableIndexes('issue');
        if (!isset($indexes['idx_issue_project_last_environment'])) {
            $this->addSql('CREATE INDEX idx_issue_project_last_environment ON issue (project_id, last_environment)');
        }
    }

    public function down(Schema $schema): void
    {
        $indexes = $this->connection->createSchemaManager()->listTableIndexes('issue');
        if (isset($indexes['idx_issue_project_last_environment'])) {
            $this->addSql('DROP INDEX idx_issue_project_last_environment ON issue');
        }
    }
}
