<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Optional Slack signing secret on notification destinations (interactive Resolve).
 */
final class Version20260731180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'notification_destination.signing_secret for Slack interactive actions';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $table = $sm->introspectTable('notification_destination');
        if (!$table->hasColumn('signing_secret')) {
            $this->addSql('ALTER TABLE notification_destination ADD signing_secret LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $table = $sm->introspectTable('notification_destination');
        if ($table->hasColumn('signing_secret')) {
            $this->addSql('ALTER TABLE notification_destination DROP signing_secret');
        }
    }
}
