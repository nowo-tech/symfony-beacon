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
 * Instance RBAC: permission catalog, named roles, user↔role and role↔permission links.
 *
 * Originally created as instance_* tables (renamed in Version20260809160000 to
 * permission / role / role_permission / role_user).
 */
final class Version20260809120000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create instance_permission, instance_role, and assignment join tables';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['instance_permission'])) {
            return;
        }

        $this->applyMdk([
            MDK::TABLES => [
                'instance_permission' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'uuid', 'type' => 'string', 'length' => 36, 'notnull' => true],
                        ['name' => 'permission_key', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'name', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'description', 'type' => 'text', 'notnull' => false],
                        ['name' => 'category', 'type' => 'string', 'length' => 60, 'notnull' => true],
                        ['name' => 'is_system', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        AuditFields::createdAt(true),
                        AuditFields::updatedAt(false),
                        ...AuditFields::blameColumns(),
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['uuid'], 'unique' => true, 'name' => 'uniq_instance_permission_uuid'],
                        ['columns' => ['permission_key'], 'unique' => true, 'name' => 'uniq_instance_permission_key'],
                        ['columns' => ['category'], 'name' => 'idx_instance_permission_category'],
                        ['columns' => ['created_by_id'], 'name' => 'IDX_INSTANCE_PERMISSION_CREATED_BY'],
                        ['columns' => ['updated_by_id'], 'name' => 'IDX_INSTANCE_PERMISSION_UPDATED_BY'],
                    ],
                    MDK::FOREIGN_KEYS => AuditFields::blameForeignKeys(
                        'app_user',
                        'FK_INSTANCE_PERMISSION_CREATED_BY',
                        'FK_INSTANCE_PERMISSION_UPDATED_BY',
                    ),
                ],
                'instance_role' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'uuid', 'type' => 'string', 'length' => 36, 'notnull' => true],
                        ['name' => 'name', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'code', 'type' => 'string', 'length' => 60, 'notnull' => true],
                        ['name' => 'description', 'type' => 'text', 'notnull' => false],
                        ['name' => 'enabled', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'is_system', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        AuditFields::createdAt(true),
                        AuditFields::updatedAt(false),
                        ...AuditFields::blameColumns(),
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['uuid'], 'unique' => true, 'name' => 'uniq_instance_role_uuid'],
                        ['columns' => ['code'], 'unique' => true, 'name' => 'uniq_instance_role_code'],
                        ['columns' => ['created_by_id'], 'name' => 'IDX_INSTANCE_ROLE_CREATED_BY'],
                        ['columns' => ['updated_by_id'], 'name' => 'IDX_INSTANCE_ROLE_UPDATED_BY'],
                    ],
                    MDK::FOREIGN_KEYS => AuditFields::blameForeignKeys(
                        'app_user',
                        'FK_INSTANCE_ROLE_CREATED_BY',
                        'FK_INSTANCE_ROLE_UPDATED_BY',
                    ),
                ],
                'instance_role_permission' => [
                    MDK::COLUMNS => [
                        ['name' => 'role_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'permission_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['role_id', 'permission_id']]],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['role_id'],
                            'foreign_table' => 'instance_role',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_INSTANCE_ROLE_PERMISSION_ROLE',
                        ],
                        [
                            'columns' => ['permission_id'],
                            'foreign_table' => 'instance_permission',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_INSTANCE_ROLE_PERMISSION_PERM',
                        ],
                    ],
                ],
                'instance_user_role' => [
                    MDK::COLUMNS => [
                        ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'role_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['user_id', 'role_id']]],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['user_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_INSTANCE_USER_ROLE_USER',
                        ],
                        [
                            'columns' => ['role_id'],
                            'foreign_table' => 'instance_role',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_INSTANCE_USER_ROLE_ROLE',
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
                'instance_user_role',
                'instance_role_permission',
                'instance_role',
                'instance_permission',
            ],
        ]);
    }
}
