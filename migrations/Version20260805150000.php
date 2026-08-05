<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Site appearance border strength (subtle / medium / strong).
 */
final class Version20260805150000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add site_appearance.border_strength for UI border definition';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'site_appearance' => [
                    MDK::COLUMNS => [
                        ['name' => 'border_strength', 'type' => 'string', 'length' => 20, 'notnull' => true, 'default' => 'medium'],
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
                        'border_strength',
                    ],
                ],
            ],
        ]);
    }
}
