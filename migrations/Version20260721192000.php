<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Create notification_delivery_attempt for last-N delivery history (030).
 */
final class Version20260721192000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create notification_delivery_attempt for last-N delivery history';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'notification_delivery_attempt' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'destination_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'attempted_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'successful', 'type' => 'boolean', 'notnull' => true],
                        ['name' => 'error_snippet', 'type' => 'text', 'notnull' => false],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        [
                            'columns' => ['destination_id', 'attempted_at'],
                            'name' => 'idx_notif_delivery_attempt_destination_time',
                        ],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['destination_id'],
                            'foreign_table' => 'notification_destination',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_NOTIF_DELIVERY_DEST',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['notification_delivery_attempt'],
        ]);
    }
}
