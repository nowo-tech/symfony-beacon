<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use DoctrineMigrations\FieldDictionary\AuditFields;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Initial Beacon schema (users, projects, issues, events, performance, stats).
 */
final class Version20260719112345 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create core Beacon tables (user, project, issue, event, performance, stats)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'app_user' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'email', 'type' => 'string', 'length' => 180, 'notnull' => true],
                        ['name' => 'display_name', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'roles', 'type' => 'json', 'notnull' => true],
                        ['name' => 'password', 'type' => 'string', 'length' => 255, 'notnull' => true],
                        AuditFields::createdAt(true),
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['email'], 'unique' => true, 'name' => 'uniq_user_email'],
                    ],
                ],
                'project' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'name', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'slug', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'description', 'type' => 'text', 'notnull' => false],
                        AuditFields::createdAt(true),
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['slug'], 'unique' => true, 'name' => 'uniq_project_slug'],
                    ],
                ],
                'project_api_key' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'public_key', 'type' => 'string', 'length' => 64, 'notnull' => true],
                        ['name' => 'secret_key', 'type' => 'string', 'length' => 64, 'notnull' => false],
                        ['name' => 'label', 'type' => 'string', 'length' => 80, 'notnull' => true],
                        ['name' => 'active', 'type' => 'boolean', 'notnull' => true],
                        AuditFields::createdAt(true),
                        ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['project_id'], 'name' => 'IDX_5F0CE1F8166D1F9C'],
                        ['columns' => ['public_key'], 'unique' => true, 'name' => 'uniq_api_key_public'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['project_id'],
                            'foreign_table' => 'project',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_5F0CE1F8166D1F9C',
                        ],
                    ],
                ],
                'project_membership' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'role', 'type' => 'string', 'length' => 20, 'notnull' => true],
                        AuditFields::createdAt(true),
                        ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['project_id'], 'name' => 'IDX_9E59A9B7166D1F9C'],
                        ['columns' => ['user_id'], 'name' => 'IDX_9E59A9B7A76ED395'],
                        ['columns' => ['project_id', 'user_id'], 'unique' => true, 'name' => 'uniq_project_user'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['project_id'],
                            'foreign_table' => 'project',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_9E59A9B7166D1F9C',
                        ],
                        [
                            'columns' => ['user_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_9E59A9B7A76ED395',
                        ],
                    ],
                ],
                'issue' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'fingerprint', 'type' => 'string', 'length' => 64, 'notnull' => true],
                        ['name' => 'title', 'type' => 'string', 'length' => 500, 'notnull' => true],
                        ['name' => 'culprit', 'type' => 'string', 'length' => 40, 'notnull' => true],
                        ['name' => 'level', 'type' => 'string', 'length' => 20, 'notnull' => true],
                        ['name' => 'status', 'type' => 'string', 'length' => 20, 'notnull' => true],
                        ['name' => 'event_count', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'first_seen', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'last_seen', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['project_id'], 'name' => 'IDX_12AD233E166D1F9C'],
                        ['columns' => ['project_id', 'last_seen'], 'name' => 'idx_issue_project_last_seen'],
                        ['columns' => ['project_id', 'status'], 'name' => 'idx_issue_project_status'],
                        ['columns' => ['project_id', 'fingerprint'], 'unique' => true, 'name' => 'uniq_project_fingerprint'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['project_id'],
                            'foreign_table' => 'project',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_12AD233E166D1F9C',
                        ],
                    ],
                ],
                'event' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'event_id', 'type' => 'string', 'length' => 64, 'notnull' => true],
                        ['name' => 'environment', 'type' => 'string', 'length' => 80, 'notnull' => false],
                        ['name' => 'release_version', 'type' => 'string', 'length' => 120, 'notnull' => false],
                        ['name' => 'platform', 'type' => 'string', 'length' => 40, 'notnull' => true],
                        ['name' => 'payload', 'type' => 'json', 'notnull' => true],
                        ['name' => 'event_timestamp', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'received_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'issue_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['issue_id'], 'name' => 'IDX_3BAE0AA75E7AA58C'],
                        ['columns' => ['issue_id', 'received_at'], 'name' => 'idx_event_issue_received'],
                        ['columns' => ['event_id'], 'unique' => true, 'name' => 'uniq_event_id'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['issue_id'],
                            'foreign_table' => 'issue',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_3BAE0AA75E7AA58C',
                        ],
                    ],
                ],
                'perf_transaction' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'event_id', 'type' => 'string', 'length' => 64, 'notnull' => true],
                        ['name' => 'transaction_name', 'type' => 'string', 'length' => 200, 'notnull' => true],
                        ['name' => 'duration_ms', 'type' => 'float', 'notnull' => true],
                        ['name' => 'span_count', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'n_plus_one_count', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'payload', 'type' => 'json', 'notnull' => true],
                        ['name' => 'received_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['project_id'], 'name' => 'IDX_B02C000F166D1F9C'],
                        ['columns' => ['project_id', 'received_at'], 'name' => 'idx_tx_project_received'],
                        ['columns' => ['project_id', 'n_plus_one_count'], 'name' => 'idx_tx_nplus1'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['project_id'],
                            'foreign_table' => 'project',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_B02C000F166D1F9C',
                        ],
                    ],
                ],
                'perf_span' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'span_id', 'type' => 'string', 'length' => 32, 'notnull' => true],
                        ['name' => 'op', 'type' => 'string', 'length' => 80, 'notnull' => true],
                        ['name' => 'description', 'type' => 'string', 'length' => 500, 'notnull' => true],
                        ['name' => 'duration_ms', 'type' => 'float', 'notnull' => true],
                        ['name' => 'n_plus_one_candidate', 'type' => 'boolean', 'notnull' => true],
                        ['name' => 'transaction_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['transaction_id'], 'name' => 'IDX_6A2F7D602FC0CB0F'],
                        ['columns' => ['transaction_id', 'op'], 'name' => 'idx_span_tx_op'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['transaction_id'],
                            'foreign_table' => 'perf_transaction',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_6A2F7D602FC0CB0F',
                        ],
                    ],
                ],
                'daily_project_stat' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'stat_date', 'type' => 'date', 'notnull' => true],
                        ['name' => 'error_count', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'transaction_count', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'n_plus_one_count', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['project_id'], 'name' => 'IDX_94F57C1F166D1F9C'],
                        ['columns' => ['project_id', 'stat_date'], 'unique' => true, 'name' => 'uniq_project_stat_day'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['project_id'],
                            'foreign_table' => 'project',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_94F57C1F166D1F9C',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => [
                'daily_project_stat',
                'perf_span',
                'perf_transaction',
                'event',
                'issue',
                'project_membership',
                'project_api_key',
                'project',
                'app_user',
            ],
        ]);
    }
}
