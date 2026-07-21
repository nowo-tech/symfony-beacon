<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Cookie consent log (+ optional config/inventory tables mapped by nowo-tech/cookie-consent-bundle).
 */
final class Version20260720183000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create nowo-tech cookie consent tables (log, config, definitions)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'dashboard_cookie_log' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'ip_address', 'type' => 'string', 'length' => 255, 'notnull' => true],
                        ['name' => 'cookie_consent_key', 'type' => 'string', 'length' => 255, 'notnull' => true],
                        ['name' => 'cookie_name', 'type' => 'string', 'length' => 255, 'notnull' => true],
                        ['name' => 'cookie_value', 'type' => 'boolean', 'notnull' => true],
                        ['name' => 'timestamp', 'type' => 'datetime_immutable', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                ],
                'dashboard_cookie_config' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'enabled', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'is_default', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'auto_show', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'revision', 'type' => 'integer', 'notnull' => true, 'default' => 0],
                        ['name' => 'manage_script_tags', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'auto_clear_cookies', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'hide_from_bots', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'disable_page_interaction', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'lazy_html_generation', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'consent_modal_layout', 'type' => 'string', 'length' => 20, 'notnull' => true, 'default' => 'box'],
                        ['name' => 'consent_modal_variant', 'type' => 'string', 'length' => 20, 'notnull' => true, 'default' => 'wide'],
                        ['name' => 'consent_modal_position_y', 'type' => 'string', 'length' => 20, 'notnull' => true, 'default' => 'bottom'],
                        ['name' => 'consent_modal_position_x', 'type' => 'string', 'length' => 20, 'notnull' => false, 'default' => 'center'],
                        ['name' => 'consent_modal_equal_weight_buttons', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'consent_modal_flip_buttons', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'auto_show_route_mode', 'type' => 'string', 'length' => 20, 'notnull' => true, 'default' => 'all'],
                        ['name' => 'auto_show_routes', 'type' => 'json', 'notnull' => true],
                        ['name' => 'name', 'type' => 'string', 'length' => 100, 'notnull' => false],
                        ['name' => 'route_patterns', 'type' => 'json', 'notnull' => true],
                        ['name' => 'priority', 'type' => 'integer', 'notnull' => true, 'default' => 0],
                        ['name' => 'preferences_modal_layout', 'type' => 'string', 'length' => 20, 'notnull' => true, 'default' => 'box'],
                        ['name' => 'preferences_modal_variant', 'type' => 'string', 'length' => 20, 'notnull' => true, 'default' => 'wide'],
                        ['name' => 'preferences_modal_position_y', 'type' => 'string', 'length' => 20, 'notnull' => true, 'default' => 'middle'],
                        ['name' => 'preferences_modal_position_x', 'type' => 'string', 'length' => 20, 'notnull' => false, 'default' => 'center'],
                        ['name' => 'preferences_modal_equal_weight_buttons', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'preferences_modal_flip_buttons', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'color_theme', 'type' => 'string', 'length' => 30, 'notnull' => true, 'default' => 'light'],
                        ['name' => 'dark_mode_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'disable_transitions', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'two_step_modal', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'open_preferences_modal', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'manage_iframe_placeholders', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'granular_cookie_selection', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'preferences_bubble_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'preferences_bubble_position', 'type' => 'string', 'length' => 20, 'notnull' => true, 'default' => 'bottom-right'],
                        ['name' => 'preferences_bubble_border_color', 'type' => 'string', 'length' => 7, 'notnull' => false],
                        ['name' => 'preferences_bubble_icon', 'type' => 'text', 'notnull' => false],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                ],
                'dashboard_cookie_config_translation' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'locale', 'type' => 'string', 'length' => 10, 'notnull' => true],
                        ['name' => 'consent_modal_label', 'type' => 'string', 'length' => 100, 'notnull' => false],
                        ['name' => 'consent_modal_title', 'type' => 'string', 'length' => 100, 'notnull' => true],
                        ['name' => 'consent_modal_description', 'type' => 'text', 'notnull' => true],
                        ['name' => 'consent_modal_accept_all_btn', 'type' => 'string', 'length' => 30, 'notnull' => true],
                        ['name' => 'consent_modal_accept_necessary_btn', 'type' => 'string', 'length' => 30, 'notnull' => true],
                        ['name' => 'consent_modal_show_preferences_btn', 'type' => 'string', 'length' => 30, 'notnull' => false],
                        ['name' => 'consent_modal_footer', 'type' => 'text', 'notnull' => false],
                        ['name' => 'preferences_modal_title', 'type' => 'string', 'length' => 100, 'notnull' => false],
                        ['name' => 'preferences_modal_accept_all_btn', 'type' => 'string', 'length' => 30, 'notnull' => false],
                        ['name' => 'preferences_modal_accept_necessary_btn', 'type' => 'string', 'length' => 30, 'notnull' => false],
                        ['name' => 'preferences_modal_save_preferences_btn', 'type' => 'string', 'length' => 30, 'notnull' => false],
                        ['name' => 'preferences_modal_close_icon_label', 'type' => 'string', 'length' => 30, 'notnull' => false],
                        ['name' => 'privacy_route', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'preference_sections', 'type' => 'json', 'notnull' => false],
                        ['name' => 'config_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['config_id'], 'name' => 'IDX_COOKIE_CONSENT_CFG_TR_CFG'],
                        ['columns' => ['config_id', 'locale'], 'unique' => true, 'name' => 'uniq_cookie_consent_config_translation_locale'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['config_id'],
                            'foreign_table' => 'dashboard_cookie_config',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_COOKIE_CONSENT_CFG_TR_CFG',
                        ],
                    ],
                ],
                'dashboard_cookie_definition' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'name', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'duration', 'type' => 'string', 'length' => 60, 'notnull' => true],
                        ['name' => 'category', 'type' => 'string', 'length' => 40, 'notnull' => true],
                        ['name' => 'type', 'type' => 'string', 'length' => 20, 'notnull' => true],
                        ['name' => 'sort_order', 'type' => 'integer', 'notnull' => true, 'default' => 0],
                        ['name' => 'allowed_by_default', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'config_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['config_id'], 'name' => 'IDX_COOKIE_DEF_CFG'],
                        ['columns' => ['config_id', 'name'], 'unique' => true, 'name' => 'uniq_cookie_definition_config_name'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['config_id'],
                            'foreign_table' => 'dashboard_cookie_config',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_COOKIE_DEF_CFG',
                        ],
                    ],
                ],
                'dashboard_cookie_definition_translation' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'locale', 'type' => 'string', 'length' => 10, 'notnull' => true],
                        ['name' => 'provider', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'purpose', 'type' => 'text', 'notnull' => true],
                        ['name' => 'definition_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['definition_id'], 'name' => 'IDX_COOKIE_DEF_TR_DEF'],
                        ['columns' => ['definition_id', 'locale'], 'unique' => true, 'name' => 'uniq_cookie_definition_translation_locale'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['definition_id'],
                            'foreign_table' => 'dashboard_cookie_definition',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_COOKIE_DEF_TR_DEF',
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
                'dashboard_cookie_definition_translation',
                'dashboard_cookie_definition',
                'dashboard_cookie_config_translation',
                'dashboard_cookie_config',
                'dashboard_cookie_log',
            ],
        ]);
    }
}
