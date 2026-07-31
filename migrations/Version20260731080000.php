<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Per-project monthly event quota override (`032-monthly-quota`).
 */
final class Version20260731080000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add project.event_quota_monthly (nullable; inherits BEACON_EVENT_QUOTA_MONTHLY)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'project' => [
                    MDK::COLUMNS => [
                        ['name' => 'event_quota_monthly', 'type' => 'integer', 'notnull' => false],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'project' => [
                    MDK::DROP_COLUMNS => [
                        'event_quota_monthly',
                    ],
                ],
            ],
        ]);
    }
}
