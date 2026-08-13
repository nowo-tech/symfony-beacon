<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Store ingest API key secrets as SHA-256 hashes (plaintext only in one-shot DSN flash).
 */
final class Version20260813190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project_api_key.secret_hash for hash-at-rest ingest secrets';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('project_api_key');
        if (!$table->hasColumn('secret_hash')) {
            $this->addSql('ALTER TABLE project_api_key ADD secret_hash VARCHAR(64) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('project_api_key');
        if ($table->hasColumn('secret_hash')) {
            $this->addSql('ALTER TABLE project_api_key DROP secret_hash');
        }
    }
}
