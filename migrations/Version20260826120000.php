<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Widen issue.culprit so typical PHP Class::method names are not clipped (107).
 */
final class Version20260826120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen issue.culprit from VARCHAR(40) to VARCHAR(255)';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['issue'])) {
            return;
        }

        $platform = $this->connection->getDatabasePlatform();
        if (!$platform instanceof AbstractMySQLPlatform) {
            return;
        }

        $this->addSql('ALTER TABLE issue CHANGE culprit culprit VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['issue'])) {
            return;
        }

        $platform = $this->connection->getDatabasePlatform();
        if (!$platform instanceof AbstractMySQLPlatform) {
            return;
        }

        $this->addSql('ALTER TABLE issue CHANGE culprit culprit VARCHAR(40) NOT NULL');
    }
}
