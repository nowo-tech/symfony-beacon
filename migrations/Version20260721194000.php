<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Extra account appearance prefs: font scale, contrast, sidebar default.
 */
final class Version20260721194000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'User appearance prefs: font scale, contrast, sidebar default';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'app_user' => [
                    MDK::COLUMNS => [
                        ['name' => 'preferred_font_scale', 'type' => 'string', 'length' => 8, 'notnull' => false],
                        ['name' => 'preferred_contrast', 'type' => 'string', 'length' => 8, 'notnull' => false],
                        ['name' => 'preferred_sidebar', 'type' => 'string', 'length' => 16, 'notnull' => false],
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
                    MDK::DROP_COLUMNS => [
                        'preferred_font_scale',
                        'preferred_contrast',
                        'preferred_sidebar',
                    ],
                ],
            ],
        ]);
    }
}
