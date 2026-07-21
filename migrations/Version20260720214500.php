<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Assign issues to a project member (who owns / resolves the issue).
 */
final class Version20260720214500 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add nullable issue.assignee_id FK to app_user';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'issue' => [
                    MDK::COLUMNS => [
                        ['name' => 'assignee_id', 'type' => 'integer', 'notnull' => false],
                    ],
                    MDK::INDEXES => [
                        ['columns' => ['project_id', 'assignee_id'], 'name' => 'idx_issue_project_assignee'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['assignee_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_SET_NULL,
                            'name' => 'FK_ISSUE_ASSIGNEE',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'issue' => [
                    MDK::DROP_FOREIGN_KEYS => ['FK_ISSUE_ASSIGNEE'],
                    MDK::DROP_INDEXES => ['idx_issue_project_assignee'],
                    MDK::DROP_COLUMNS => ['assignee_id'],
                ],
            ],
        ]);
    }
}
