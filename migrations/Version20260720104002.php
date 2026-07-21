<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Breadcrumb Kit tables (dashboard_breadcrumb_*).
 */
final class Version20260720104002 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create Breadcrumb Kit tables (dashboard_breadcrumb_*)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'dashboard_breadcrumb_collection' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'code', 'type' => 'string', 'length' => 64, 'notnull' => true],
                        ['name' => 'context_key', 'type' => 'string', 'length' => 512, 'notnull' => true, 'default' => ''],
                        ['name' => 'name', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'home_icon', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'separator_icon', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'responsive_config', 'type' => 'json', 'notnull' => false],
                        ['name' => 'class_list', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_item', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_separator', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_current', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'inline_edit_access_key', 'type' => 'string', 'length' => 64, 'notnull' => false],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['code', 'context_key'], 'unique' => true, 'name' => 'uniq_breadcrumb_collection_code_context'],
                    ],
                ],
                'dashboard_breadcrumb_item' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'route_name', 'type' => 'string', 'length' => 255, 'notnull' => true],
                        ['name' => 'static_route_params', 'type' => 'json', 'notnull' => false],
                        ['name' => 'dynamic_param_keys', 'type' => 'json', 'notnull' => false],
                        ['name' => 'link_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'label', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'translations', 'type' => 'json', 'notnull' => false],
                        ['name' => 'icon', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'collection_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'parent_id', 'type' => 'integer', 'notnull' => false],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['collection_id'], 'name' => 'IDX_A365DB8E514956FD'],
                        ['columns' => ['parent_id'], 'name' => 'IDX_A365DB8E727ACA70'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['collection_id'],
                            'foreign_table' => 'dashboard_breadcrumb_collection',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_A365DB8E514956FD',
                        ],
                        [
                            'columns' => ['parent_id'],
                            'foreign_table' => 'dashboard_breadcrumb_item',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_SET_NULL,
                            'name' => 'FK_A365DB8E727ACA70',
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
                'dashboard_breadcrumb_item',
                'dashboard_breadcrumb_collection',
            ],
        ]);
    }
}
