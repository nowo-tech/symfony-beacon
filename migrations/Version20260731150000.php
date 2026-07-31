<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

final class Version20260731150000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'project_share_link max_uses + use_count (061 share-link max uses)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'project_share_link' => [
                    MDK::COLUMNS => [
                        ['name' => 'max_uses', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'use_count', 'type' => 'integer', 'notnull' => true, 'default' => 0],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'project_share_link' => [
                    MDK::DROP_COLUMNS => ['max_uses', 'use_count'],
                ],
            ],
        ]);
    }
}
