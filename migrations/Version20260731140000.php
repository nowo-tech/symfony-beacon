<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

final class Version20260731140000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'app_user.anonymized_at for GDPR account anonymize (043)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'app_user' => [
                    MDK::COLUMNS => [
                        ['name' => 'anonymized_at', 'type' => 'datetime_immutable', 'notnull' => false],
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
                    MDK::DROP_COLUMNS => ['anonymized_at'],
                ],
            ],
        ]);
    }
}
