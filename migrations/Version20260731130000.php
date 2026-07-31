<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

final class Version20260731130000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Site appearance warn / paper / ink / surface colors (light + dark)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'site_appearance' => [
                    MDK::COLUMNS => [
                        ['name' => 'warn_color', 'type' => 'string', 'length' => 7, 'notnull' => true, 'default' => '#b54708'],
                        ['name' => 'warn_color_dark', 'type' => 'string', 'length' => 7, 'notnull' => true, 'default' => '#fdb022'],
                        ['name' => 'paper_color', 'type' => 'string', 'length' => 7, 'notnull' => true, 'default' => '#f3f6f4'],
                        ['name' => 'paper_color_dark', 'type' => 'string', 'length' => 7, 'notnull' => true, 'default' => '#0c1210'],
                        ['name' => 'ink_color', 'type' => 'string', 'length' => 7, 'notnull' => true, 'default' => '#0f1c18'],
                        ['name' => 'ink_color_dark', 'type' => 'string', 'length' => 7, 'notnull' => true, 'default' => '#e6eee9'],
                        ['name' => 'surface_color', 'type' => 'string', 'length' => 7, 'notnull' => true, 'default' => '#ffffff'],
                        ['name' => 'surface_color_dark', 'type' => 'string', 'length' => 7, 'notnull' => true, 'default' => '#151c19'],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'site_appearance' => [
                    MDK::DROP_COLUMNS => [
                        'warn_color',
                        'warn_color_dark',
                        'paper_color',
                        'paper_color_dark',
                        'ink_color',
                        'ink_color_dark',
                        'surface_color',
                        'surface_color_dark',
                    ],
                ],
            ],
        ]);
    }
}
