<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Denormalized release / environment context on issues.
 */
final class Version20260721160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add first_release, last_release, last_environment columns to issue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE issue ADD first_release VARCHAR(120) DEFAULT NULL, ADD last_release VARCHAR(120) DEFAULT NULL, ADD last_environment VARCHAR(80) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_issue_project_last_release ON issue (project_id, last_release)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_issue_project_last_release ON issue');
        $this->addSql('ALTER TABLE issue DROP first_release, DROP last_release, DROP last_environment');
    }
}
