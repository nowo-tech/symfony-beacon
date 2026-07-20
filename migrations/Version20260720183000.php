<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cookie consent log (+ optional config/inventory tables mapped by nowo-tech/cookie-consent-bundle).
 */
final class Version20260720183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create nowo-tech cookie consent tables (log, config, definitions)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dashboard_cookie_log (id INT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(255) NOT NULL, cookie_consent_key VARCHAR(255) NOT NULL, cookie_name VARCHAR(255) NOT NULL, cookie_value TINYINT(1) NOT NULL, timestamp DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dashboard_cookie_config (id INT AUTO_INCREMENT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, is_default TINYINT(1) DEFAULT 0 NOT NULL, auto_show TINYINT(1) DEFAULT 1 NOT NULL, revision INT DEFAULT 0 NOT NULL, manage_script_tags TINYINT(1) DEFAULT 0 NOT NULL, auto_clear_cookies TINYINT(1) DEFAULT 0 NOT NULL, hide_from_bots TINYINT(1) DEFAULT 1 NOT NULL, disable_page_interaction TINYINT(1) DEFAULT 0 NOT NULL, lazy_html_generation TINYINT(1) DEFAULT 0 NOT NULL, consent_modal_layout VARCHAR(20) DEFAULT \'box\' NOT NULL, consent_modal_variant VARCHAR(20) DEFAULT \'wide\' NOT NULL, consent_modal_position_y VARCHAR(20) DEFAULT \'bottom\' NOT NULL, consent_modal_position_x VARCHAR(20) DEFAULT \'center\', consent_modal_equal_weight_buttons TINYINT(1) DEFAULT 0 NOT NULL, consent_modal_flip_buttons TINYINT(1) DEFAULT 0 NOT NULL, auto_show_route_mode VARCHAR(20) DEFAULT \'all\' NOT NULL, auto_show_routes JSON NOT NULL, name VARCHAR(100) DEFAULT NULL, route_patterns JSON NOT NULL, priority INT DEFAULT 0 NOT NULL, preferences_modal_layout VARCHAR(20) DEFAULT \'box\' NOT NULL, preferences_modal_variant VARCHAR(20) DEFAULT \'wide\' NOT NULL, preferences_modal_position_y VARCHAR(20) DEFAULT \'middle\' NOT NULL, preferences_modal_position_x VARCHAR(20) DEFAULT \'center\', preferences_modal_equal_weight_buttons TINYINT(1) DEFAULT 0 NOT NULL, preferences_modal_flip_buttons TINYINT(1) DEFAULT 0 NOT NULL, color_theme VARCHAR(30) DEFAULT \'light\' NOT NULL, dark_mode_enabled TINYINT(1) DEFAULT 0 NOT NULL, disable_transitions TINYINT(1) DEFAULT 0 NOT NULL, two_step_modal TINYINT(1) DEFAULT 0 NOT NULL, open_preferences_modal TINYINT(1) DEFAULT 0 NOT NULL, manage_iframe_placeholders TINYINT(1) DEFAULT 0 NOT NULL, granular_cookie_selection TINYINT(1) DEFAULT 0 NOT NULL, preferences_bubble_enabled TINYINT(1) DEFAULT 0 NOT NULL, preferences_bubble_position VARCHAR(20) DEFAULT \'bottom-right\' NOT NULL, preferences_bubble_border_color VARCHAR(7) DEFAULT NULL, preferences_bubble_icon LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dashboard_cookie_config_translation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(10) NOT NULL, consent_modal_label VARCHAR(100) DEFAULT NULL, consent_modal_title VARCHAR(100) NOT NULL, consent_modal_description LONGTEXT NOT NULL, consent_modal_accept_all_btn VARCHAR(30) NOT NULL, consent_modal_accept_necessary_btn VARCHAR(30) NOT NULL, consent_modal_show_preferences_btn VARCHAR(30) DEFAULT NULL, consent_modal_footer LONGTEXT DEFAULT NULL, preferences_modal_title VARCHAR(100) DEFAULT NULL, preferences_modal_accept_all_btn VARCHAR(30) DEFAULT NULL, preferences_modal_accept_necessary_btn VARCHAR(30) DEFAULT NULL, preferences_modal_save_preferences_btn VARCHAR(30) DEFAULT NULL, preferences_modal_close_icon_label VARCHAR(30) DEFAULT NULL, privacy_route VARCHAR(255) DEFAULT NULL, preference_sections JSON DEFAULT NULL, config_id INT NOT NULL, INDEX IDX_COOKIE_CONSENT_CFG_TR_CFG (config_id), UNIQUE INDEX uniq_cookie_consent_config_translation_locale (config_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dashboard_cookie_definition (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, duration VARCHAR(60) NOT NULL, category VARCHAR(40) NOT NULL, type VARCHAR(20) NOT NULL, sort_order INT DEFAULT 0 NOT NULL, allowed_by_default TINYINT(1) DEFAULT 1 NOT NULL, config_id INT NOT NULL, INDEX IDX_COOKIE_DEF_CFG (config_id), UNIQUE INDEX uniq_cookie_definition_config_name (config_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dashboard_cookie_definition_translation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(10) NOT NULL, provider VARCHAR(120) NOT NULL, purpose LONGTEXT NOT NULL, definition_id INT NOT NULL, INDEX IDX_COOKIE_DEF_TR_DEF (definition_id), UNIQUE INDEX uniq_cookie_definition_translation_locale (definition_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE dashboard_cookie_config_translation ADD CONSTRAINT FK_COOKIE_CONSENT_CFG_TR_CFG FOREIGN KEY (config_id) REFERENCES dashboard_cookie_config (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dashboard_cookie_definition ADD CONSTRAINT FK_COOKIE_DEF_CFG FOREIGN KEY (config_id) REFERENCES dashboard_cookie_config (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dashboard_cookie_definition_translation ADD CONSTRAINT FK_COOKIE_DEF_TR_DEF FOREIGN KEY (definition_id) REFERENCES dashboard_cookie_definition (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_cookie_config_translation DROP FOREIGN KEY FK_COOKIE_CONSENT_CFG_TR_CFG');
        $this->addSql('ALTER TABLE dashboard_cookie_definition DROP FOREIGN KEY FK_COOKIE_DEF_CFG');
        $this->addSql('ALTER TABLE dashboard_cookie_definition_translation DROP FOREIGN KEY FK_COOKIE_DEF_TR_DEF');
        $this->addSql('DROP TABLE dashboard_cookie_definition_translation');
        $this->addSql('DROP TABLE dashboard_cookie_definition');
        $this->addSql('DROP TABLE dashboard_cookie_config_translation');
        $this->addSql('DROP TABLE dashboard_cookie_config');
        $this->addSql('DROP TABLE dashboard_cookie_log');
    }
}
