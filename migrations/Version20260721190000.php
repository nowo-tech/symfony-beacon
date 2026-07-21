<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Create project_threshold_rule for rolling error-volume alerts.
 */
final class Version20260721190000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create project_threshold_rule table for per-project error volume threshold alerts';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'project_threshold_rule' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'uuid', 'type' => 'string', 'length' => 36, 'notnull' => true],
                        ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'label', 'type' => 'string', 'length' => 120, 'notnull' => false],
                        ['name' => 'enabled', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'error_count', 'type' => 'integer', 'notnull' => true, 'default' => 50],
                        ['name' => 'window_minutes', 'type' => 'integer', 'notnull' => true, 'default' => 15],
                        ['name' => 'cooldown_minutes', 'type' => 'integer', 'notnull' => true, 'default' => 60],
                        ['name' => 'environment', 'type' => 'string', 'length' => 80, 'notnull' => false],
                        ['name' => 'release_version', 'type' => 'string', 'length' => 120, 'notnull' => false],
                        ['name' => 'last_fired_at', 'type' => 'datetime_immutable', 'notnull' => false],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'updated_at', 'type' => 'datetime_immutable', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['uuid'], 'unique' => true, 'name' => 'uniq_project_threshold_rule_uuid'],
                        ['columns' => ['project_id', 'enabled'], 'name' => 'idx_project_threshold_rule_project_enabled'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['project_id'],
                            'foreign_table' => 'project',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_PROJECT_THRESHOLD_RULE_PROJECT',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['project_threshold_rule'],
        ]);
    }
}
