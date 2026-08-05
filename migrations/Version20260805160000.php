<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Independent dark-mode theme id on site_appearance.
 */
final class Version20260805160000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add site_appearance.theme_id_dark for independent light/dark themes';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'site_appearance' => [
                    MDK::COLUMNS => [
                        ['name' => 'theme_id_dark', 'type' => 'string', 'length' => 40, 'notnull' => true, 'default' => 'custom'],
                    ],
                ],
            ],
        ]);

        // Move dark-mode preset ids out of theme_id so light selection stays independent.
        $this->addSql("UPDATE site_appearance SET theme_id_dark = theme_id, theme_id = 'custom' WHERE theme_id IN ('midnight', 'obsidian', 'aurora', 'ember')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE site_appearance SET theme_id = theme_id_dark WHERE theme_id = 'custom' AND theme_id_dark IN ('midnight', 'obsidian', 'aurora', 'ember')");

        $this->applyMdk([
            MDK::TABLES => [
                'site_appearance' => [
                    MDK::DROP_COLUMNS => [
                        'theme_id_dark',
                    ],
                ],
            ],
        ]);
    }
}
