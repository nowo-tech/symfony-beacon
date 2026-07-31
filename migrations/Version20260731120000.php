<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Notification destination circuit breaker columns (`039`).
 */
final class Version20260731120000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add notification_destination consecutive_failures + circuit_opened_at (039 circuit breaker)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'notification_destination' => [
                    MDK::COLUMNS => [
                        ['name' => 'consecutive_failures', 'type' => 'integer', 'notnull' => true, 'default' => 0],
                        ['name' => 'circuit_opened_at', 'type' => 'datetime_immutable', 'notnull' => false],
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
                        'consecutive_failures',
                        'circuit_opened_at',
                    ],
                ],
            ],
        ]);
    }
}
