<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop obsolete ingest_reject_query_auth — query-string Envelope auth is hard-removed (093).
 */
final class Version20260813100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop instance_settings.ingest_reject_query_auth (query Envelope auth removed)';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('instance_settings');
        if ($table->hasColumn('ingest_reject_query_auth')) {
            $this->addSql('ALTER TABLE instance_settings DROP ingest_reject_query_auth');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('instance_settings');
        if (!$table->hasColumn('ingest_reject_query_auth')) {
            $this->addSql('ALTER TABLE instance_settings ADD ingest_reject_query_auth TINYINT(1) DEFAULT 1 NOT NULL');
        }
    }
}
