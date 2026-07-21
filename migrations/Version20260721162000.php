<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Per-user saved filter/sort views for the project issues list.
 */
final class Version20260721162000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create issue_saved_view for named issue list filters';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'issue_saved_view' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'uuid', 'type' => 'string', 'length' => 36, 'notnull' => true],
                        ['name' => 'name', 'type' => 'string', 'length' => 80, 'notnull' => true],
                        ['name' => 'query_json', 'type' => 'json', 'notnull' => true],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['uuid'], 'unique' => true, 'name' => 'uniq_issue_saved_view_uuid'],
                        ['columns' => ['user_id', 'project_id'], 'name' => 'idx_issue_saved_view_user_project'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['user_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_ISSUE_SAVED_VIEW_USER',
                        ],
                        [
                            'columns' => ['project_id'],
                            'foreign_table' => 'project',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_ISSUE_SAVED_VIEW_PROJECT',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['issue_saved_view'],
        ]);
    }
}
