<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Dashboard Menu tables (menu + menu_item).
 */
final class Version20260720104249_CreateDashboardMenuTablesByConfiguration extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create dashboard_menu and dashboard_menu_item tables (prefix: (none))';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'dashboard_menu' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'code', 'type' => 'string', 'length' => 64, 'notnull' => true],
                        ['name' => 'attributes_key', 'type' => 'string', 'length' => 512, 'notnull' => true, 'default' => ''],
                        ['name' => 'name', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'icon', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'class_menu', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'ul_id', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'class_item', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_link', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_children', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_section_children', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_section_child_item', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_section_child_link', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_section_label', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_section', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_divider', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'class_current', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'class_branch_expanded', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'class_has_children', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'class_expanded', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'class_collapsed', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'permission_checker', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'depth_limit', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'collapsible', 'type' => 'boolean', 'notnull' => false],
                        ['name' => 'collapsible_expanded', 'type' => 'boolean', 'notnull' => false],
                        ['name' => 'nested_collapsible', 'type' => 'boolean', 'notnull' => false],
                        ['name' => 'nested_collapsible_sections', 'type' => 'boolean', 'notnull' => false],
                        ['name' => 'attributes', 'type' => 'json', 'notnull' => false],
                        ['name' => 'base', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['code', 'attributes_key'], 'unique' => true, 'name' => 'uniq_menu_code_context'],
                    ],
                ],
                'dashboard_menu_item' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'position', 'type' => 'integer', 'notnull' => true, 'default' => 0],
                        ['name' => 'label', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'translations', 'type' => 'json', 'notnull' => false],
                        ['name' => 'link_type', 'type' => 'string', 'length' => 20, 'notnull' => false, 'default' => 'route'],
                        ['name' => 'route_name', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'route_params', 'type' => 'json', 'notnull' => false],
                        ['name' => 'external_url', 'type' => 'string', 'length' => 2048, 'notnull' => false],
                        ['name' => 'permission_key', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'permission_keys', 'type' => 'json', 'notnull' => false],
                        ['name' => 'is_unanimous', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'icon', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'item_type', 'type' => 'string', 'length' => 20, 'notnull' => false, 'default' => 'link'],
                        ['name' => 'link_resolver', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'target_blank', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'section_collapsible', 'type' => 'boolean', 'notnull' => false],
                        ['name' => 'menu_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'parent_id', 'type' => 'integer', 'notnull' => false],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['menu_id'], 'name' => 'IDX_9FA38DA3CCD7E912'],
                        ['columns' => ['menu_id', 'position'], 'name' => 'idx_menu_position'],
                        ['columns' => ['parent_id'], 'name' => 'idx_parent_id'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['menu_id'],
                            'foreign_table' => 'dashboard_menu',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_9FA38DA3CCD7E912',
                        ],
                        [
                            'columns' => ['parent_id'],
                            'foreign_table' => 'dashboard_menu_item',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_9FA38DA3727ACA70',
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
                'dashboard_menu_item',
                'dashboard_menu',
            ],
        ]);
    }
}
