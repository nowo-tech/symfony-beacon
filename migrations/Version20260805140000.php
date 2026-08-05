<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Site appearance corner radius preset (cards vs controls).
 */
final class Version20260805140000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add site_appearance.corner_style for card/input border radius';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'site_appearance' => [
                    MDK::COLUMNS => [
                        ['name' => 'corner_style', 'type' => 'string', 'length' => 20, 'notnull' => true, 'default' => 'soft'],
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
                        'corner_style',
                    ],
                ],
            ],
        ]);
    }
}
