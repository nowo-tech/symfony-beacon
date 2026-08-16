<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * New users prefer browser push on by default (browser permission still required).
 *
 * Table was renamed app_user → `user` in Version20260814230000.
 */
final class Version20260816120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '`user`.push_notifications_enabled column default true (new rows only)';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['user'])) {
            return;
        }

        $this->addSql('ALTER TABLE `user` CHANGE push_notifications_enabled push_notifications_enabled TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['user'])) {
            return;
        }

        $this->addSql('ALTER TABLE `user` CHANGE push_notifications_enabled push_notifications_enabled TINYINT(1) DEFAULT 0 NOT NULL');
    }
}
