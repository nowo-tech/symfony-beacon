<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Instance operational defaults (retention, ingest rate, quotas, notification limits).
 */
final class Version20260731170000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'instance_settings ops defaults (retention, rate, quotas, notification limits)';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $table = $sm->introspectTable('instance_settings');
        $columns = [];

        if (!$table->hasColumn('retention_days')) {
            $columns[] = ['name' => 'retention_days', 'type' => 'integer', 'notnull' => true, 'default' => 0];
        }
        if (!$table->hasColumn('retention_max_events')) {
            $columns[] = ['name' => 'retention_max_events', 'type' => 'integer', 'notnull' => true, 'default' => 0];
        }
        if (!$table->hasColumn('ingest_rate_limit')) {
            $columns[] = ['name' => 'ingest_rate_limit', 'type' => 'integer', 'notnull' => true, 'default' => 120];
        }
        if (!$table->hasColumn('event_quota_daily')) {
            $columns[] = ['name' => 'event_quota_daily', 'type' => 'integer', 'notnull' => true, 'default' => 0];
        }
        if (!$table->hasColumn('event_quota_monthly')) {
            $columns[] = ['name' => 'event_quota_monthly', 'type' => 'integer', 'notnull' => true, 'default' => 0];
        }
        if (!$table->hasColumn('notification_delivery_history_limit')) {
            $columns[] = ['name' => 'notification_delivery_history_limit', 'type' => 'integer', 'notnull' => true, 'default' => 20];
        }
        if (!$table->hasColumn('notification_circuit_breaker_threshold')) {
            $columns[] = ['name' => 'notification_circuit_breaker_threshold', 'type' => 'integer', 'notnull' => true, 'default' => 5];
        }
        if (!$table->hasColumn('notification_circuit_breaker_cooldown_minutes')) {
            $columns[] = ['name' => 'notification_circuit_breaker_cooldown_minutes', 'type' => 'integer', 'notnull' => true, 'default' => 0];
        }

        if ([] === $columns) {
            return;
        }

        $this->applyMdk([
            MDK::TABLES => [
                'instance_settings' => [
                    MDK::COLUMNS => $columns,
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'instance_settings' => [
                    MDK::DROP_COLUMNS => [
                        'retention_days',
                        'retention_max_events',
                        'ingest_rate_limit',
                        'event_quota_daily',
                        'event_quota_monthly',
                        'notification_delivery_history_limit',
                        'notification_circuit_breaker_threshold',
                        'notification_circuit_breaker_cooldown_minutes',
                    ],
                ],
            ],
        ]);
    }
}
