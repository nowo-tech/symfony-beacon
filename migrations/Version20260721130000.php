<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * User groups and project↔group access (bulk membership).
 */
final class Version20260721130000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create user_group, user_group_membership, and project_group_access tables';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'user_group' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'uuid', 'type' => 'string', 'length' => 36, 'notnull' => true],
                        ['name' => 'name', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'slug', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'description', 'type' => 'text', 'notnull' => false],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['uuid'], 'unique' => true, 'name' => 'uniq_user_group_uuid'],
                        ['columns' => ['slug'], 'unique' => true, 'name' => 'uniq_user_group_slug'],
                    ],
                ],
                'user_group_membership' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'user_group_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['user_group_id', 'user_id'], 'unique' => true, 'name' => 'uniq_user_group_membership'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['user_group_id'],
                            'foreign_table' => 'user_group',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_USER_GROUP_MEMBERSHIP_GROUP',
                        ],
                        [
                            'columns' => ['user_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_USER_GROUP_MEMBERSHIP_USER',
                        ],
                    ],
                ],
                'project_group_access' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'uuid', 'type' => 'string', 'length' => 36, 'notnull' => true],
                        ['name' => 'role', 'type' => 'string', 'length' => 20, 'notnull' => true],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'user_group_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['uuid'], 'unique' => true, 'name' => 'uniq_project_group_access_uuid'],
                        ['columns' => ['project_id', 'user_group_id'], 'unique' => true, 'name' => 'uniq_project_group_access'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['project_id'],
                            'foreign_table' => 'project',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_PROJECT_GROUP_ACCESS_PROJECT',
                        ],
                        [
                            'columns' => ['user_group_id'],
                            'foreign_table' => 'user_group',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_PROJECT_GROUP_ACCESS_GROUP',
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
                'project_group_access',
                'user_group_membership',
                'user_group',
            ],
        ]);
    }
}
