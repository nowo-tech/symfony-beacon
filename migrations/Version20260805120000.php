<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Named appearance theme id on site_appearance.
 */
final class Version20260805120000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add site_appearance.theme_id for named palette presets';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'site_appearance' => [
                    MDK::COLUMNS => [
                        ['name' => 'theme_id', 'type' => 'string', 'length' => 40, 'notnull' => true, 'default' => 'beacon'],
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
                        'theme_id',
                    ],
                ],
            ],
        ]);
    }
}
