<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * New users prefer browser push on by default (browser permission still required).
 */
final class Version20260816120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'app_user.push_notifications_enabled column default true (new rows only)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user CHANGE push_notifications_enabled push_notifications_enabled TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user CHANGE push_notifications_enabled push_notifications_enabled TINYINT(1) DEFAULT 0 NOT NULL');
    }
}
