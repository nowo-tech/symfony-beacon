<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Issue priority, comments, and mark-as-duplicate link.
 */
final class Version20260721161000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add issue.priority, issue.duplicate_of_id, and issue_comment table';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'issue' => [
                    MDK::COLUMNS => [
                        ['name' => 'priority', 'type' => 'string', 'length' => 20, 'notnull' => true, 'default' => 'medium'],
                        ['name' => 'duplicate_of_id', 'type' => 'integer', 'notnull' => false],
                    ],
                    MDK::INDEXES => [
                        ['columns' => ['project_id', 'priority'], 'name' => 'idx_issue_project_priority'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['duplicate_of_id'],
                            'foreign_table' => 'issue',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_SET_NULL,
                            'name' => 'FK_ISSUE_DUPLICATE_OF',
                        ],
                    ],
                ],
                'issue_comment' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'uuid', 'type' => 'string', 'length' => 36, 'notnull' => true],
                        ['name' => 'body', 'type' => 'text', 'notnull' => true],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'issue_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'author_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['uuid'], 'unique' => true, 'name' => 'uniq_issue_comment_uuid'],
                        ['columns' => ['issue_id', 'created_at'], 'name' => 'idx_issue_comment_issue_created'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['issue_id'],
                            'foreign_table' => 'issue',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_ISSUE_COMMENT_ISSUE',
                        ],
                        [
                            'columns' => ['author_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_ISSUE_COMMENT_AUTHOR',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['issue_comment'],
            MDK::TABLES => [
                'issue' => [
                    MDK::DROP_FOREIGN_KEYS => ['FK_ISSUE_DUPLICATE_OF'],
                    MDK::DROP_INDEXES => ['idx_issue_project_priority'],
                    MDK::DROP_COLUMNS => ['priority', 'duplicate_of_id'],
                ],
            ],
        ]);
    }
}
