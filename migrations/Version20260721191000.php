<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * MySQL FULLTEXT index on issue.title + issue.culprit for `q` search (029).
 * Skipped on SQLite (tests use LIKE fallback).
 */
final class Version20260721191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FULLTEXT index on issue (title, culprit) for MySQL issue search';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'FULLTEXT index is MySQL-only; SQLite keeps LIKE fallback.',
        );

        $this->addSql('CREATE FULLTEXT INDEX idx_issue_title_culprit_ft ON issue (title, culprit)');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'FULLTEXT index is MySQL-only.',
        );

        $this->addSql('DROP INDEX idx_issue_title_culprit_ft ON issue');
    }
}
