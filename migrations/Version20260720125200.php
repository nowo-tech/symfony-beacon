<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

final class Version20260720125200 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create site_appearance for ROLE_ADMIN look & feel customization';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'site_appearance' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'brand_name', 'type' => 'string', 'length' => 80, 'notnull' => true],
                        ['name' => 'brand_eyebrow', 'type' => 'string', 'length' => 80, 'notnull' => true],
                        ['name' => 'accent_color', 'type' => 'string', 'length' => 7, 'notnull' => true],
                        ['name' => 'accent_deep_color', 'type' => 'string', 'length' => 7, 'notnull' => true],
                        ['name' => 'accent_color_dark', 'type' => 'string', 'length' => 7, 'notnull' => true],
                        ['name' => 'accent_deep_color_dark', 'type' => 'string', 'length' => 7, 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['id']]],
                ],
            ],
        ]);

        $this->addSql("INSERT INTO site_appearance (id, brand_name, brand_eyebrow, accent_color, accent_deep_color, accent_color_dark, accent_deep_color_dark) VALUES (1, 'symfony-beacon', 'Error tracking', '#1f6f54', '#134736', '#4aad7f', '#6bc49a')");
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['site_appearance'],
        ]);
    }
}
