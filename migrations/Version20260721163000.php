<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Per-project governance: retention, rate limit, daily quota, and ingest suspend flag.
 */
final class Version20260721163000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add project governance columns (retention, rate limit, quota, ingestEnabled)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'project' => [
                    MDK::COLUMNS => [
                        ['name' => 'retention_days', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'retention_max_events', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'ingest_rate_limit_per_minute', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'event_quota_daily', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'ingest_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'project' => [
                    MDK::DROP_COLUMNS => [
                        'retention_days',
                        'retention_max_events',
                        'ingest_rate_limit_per_minute',
                        'event_quota_daily',
                        'ingest_enabled',
                    ],
                ],
            ],
        ]);
    }
}
