<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Issue assignment / status change timeline.
 */
final class Version20260721110000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create issue_history table for assignee and status change timeline';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'issue_history' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'kind', 'type' => 'string', 'length' => 32, 'notnull' => true],
                        ['name' => 'from_status', 'type' => 'string', 'length' => 20, 'notnull' => false],
                        ['name' => 'to_status', 'type' => 'string', 'length' => 20, 'notnull' => false],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'issue_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'actor_id', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'from_assignee_id', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'to_assignee_id', 'type' => 'integer', 'notnull' => false],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['issue_id', 'created_at'], 'name' => 'idx_issue_history_issue_created'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['issue_id'],
                            'foreign_table' => 'issue',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_ISSUE_HISTORY_ISSUE',
                        ],
                        [
                            'columns' => ['actor_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_SET_NULL,
                            'name' => 'FK_ISSUE_HISTORY_ACTOR',
                        ],
                        [
                            'columns' => ['from_assignee_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_SET_NULL,
                            'name' => 'FK_ISSUE_HISTORY_FROM_ASSIGNEE',
                        ],
                        [
                            'columns' => ['to_assignee_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_SET_NULL,
                            'name' => 'FK_ISSUE_HISTORY_TO_ASSIGNEE',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['issue_history'],
        ]);
    }
}
