<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Last delivery status on notification destinations for project health UI.
 */
final class Version20260721165000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add last delivery status columns on notification_destination';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'notification_destination' => [
                    MDK::COLUMNS => [
                        ['name' => 'last_delivery_at', 'type' => 'datetime_immutable', 'notnull' => false],
                        ['name' => 'last_delivery_success', 'type' => 'boolean', 'notnull' => false],
                        ['name' => 'last_delivery_error', 'type' => 'text', 'notnull' => false],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'notification_destination' => [
                    MDK::DROP_COLUMNS => [
                        'last_delivery_at',
                        'last_delivery_success',
                        'last_delivery_error',
                    ],
                ],
            ],
        ]);
    }
}
