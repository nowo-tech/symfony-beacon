<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Create project_share_link for read-only share URLs (026).
 */
final class Version20260721180000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create project_share_link table for read-only viewer share links';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'project_share_link' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'uuid', 'type' => 'string', 'length' => 36, 'notnull' => true],
                        ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'issue_id', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'created_by_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'token_hash', 'type' => 'string', 'length' => 64, 'notnull' => true],
                        ['name' => 'expires_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'revoked_at', 'type' => 'datetime_immutable', 'notnull' => false],
                        ['name' => 'last_used_at', 'type' => 'datetime_immutable', 'notnull' => false],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['uuid'], 'unique' => true, 'name' => 'uniq_project_share_link_uuid'],
                        ['columns' => ['token_hash'], 'unique' => true, 'name' => 'uniq_project_share_link_token'],
                        ['columns' => ['project_id'], 'name' => 'idx_project_share_link_project'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['project_id'],
                            'foreign_table' => 'project',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_PROJECT_SHARE_LINK_PROJECT',
                        ],
                        [
                            'columns' => ['issue_id'],
                            'foreign_table' => 'issue',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_PROJECT_SHARE_LINK_ISSUE',
                        ],
                        [
                            'columns' => ['created_by_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_PROJECT_SHARE_LINK_USER',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['project_share_link'],
        ]);
    }
}
