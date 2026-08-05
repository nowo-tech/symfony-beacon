<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Optional fixed legal footer on site_appearance.
 */
final class Version20260805130000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add site_appearance.footer_fixed for pinned legal footer';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'site_appearance' => [
                    MDK::COLUMNS => [
                        ['name' => 'footer_fixed', 'type' => 'boolean', 'notnull' => true, 'default' => false],
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
                        'footer_fixed',
                    ],
                ],
            ],
        ]);
    }
}
