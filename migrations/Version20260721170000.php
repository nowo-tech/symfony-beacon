<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

final class Version20260721170000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'User UI density + motion prefs; site appearance danger/alert colors';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'app_user' => [
                    MDK::COLUMNS => [
                        ['name' => 'preferred_ui_density', 'type' => 'string', 'length' => 16, 'notnull' => false],
                        ['name' => 'preferred_motion', 'type' => 'string', 'length' => 16, 'notnull' => false],
                    ],
                ],
                'site_appearance' => [
                    MDK::COLUMNS => [
                        ['name' => 'danger_color', 'type' => 'string', 'length' => 7, 'notnull' => true, 'default' => '#b42318'],
                        ['name' => 'danger_color_dark', 'type' => 'string', 'length' => 7, 'notnull' => true, 'default' => '#f97066'],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'app_user' => [
                    MDK::DROP_COLUMNS => ['preferred_ui_density', 'preferred_motion'],
                ],
                'site_appearance' => [
                    MDK::DROP_COLUMNS => ['danger_color', 'danger_color_dark'],
                ],
            ],
        ]);
    }
}
