<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rich event context: microsecond timestamps and promoted runtime/user columns.
 */
final class Version20260720200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event context columns and DATETIME(6) precision for event timestamps';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD php_version VARCHAR(40) DEFAULT NULL, ADD symfony_version VARCHAR(40) DEFAULT NULL, ADD user_identifier VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE event CHANGE event_timestamp event_timestamp DATETIME(6) NOT NULL, CHANGE received_at received_at DATETIME(6) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP php_version, DROP symfony_version, DROP user_identifier');
        $this->addSql('ALTER TABLE event CHANGE event_timestamp event_timestamp DATETIME NOT NULL, CHANGE received_at received_at DATETIME NOT NULL');
    }
}
