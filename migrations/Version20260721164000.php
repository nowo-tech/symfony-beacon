<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Quiet hours / digest settings on destinations + buffer table for deferred alerts.
 */
final class Version20260721164000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add quiet hours/digest columns on notification_destination and notification_digest_buffer table';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'notification_destination' => [
                    MDK::COLUMNS => [
                        ['name' => 'quiet_hours_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'quiet_hours_timezone', 'type' => 'string', 'length' => 64, 'notnull' => true, 'default' => 'UTC'],
                        ['name' => 'quiet_hours_start', 'type' => 'string', 'length' => 5, 'notnull' => false],
                        ['name' => 'quiet_hours_end', 'type' => 'string', 'length' => 5, 'notnull' => false],
                        ['name' => 'digest_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                    ],
                ],
                'notification_digest_buffer' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'destination_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'payload', 'type' => 'json', 'notnull' => true],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['destination_id', 'created_at'], 'name' => 'idx_notif_digest_buffer_dest_created'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['destination_id'],
                            'foreign_table' => 'notification_destination',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_NOTIF_DIGEST_BUFFER_DEST',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['notification_digest_buffer'],
            MDK::TABLES => [
                'notification_destination' => [
                    MDK::DROP_COLUMNS => [
                        'quiet_hours_enabled',
                        'quiet_hours_timezone',
                        'quiet_hours_start',
                        'quiet_hours_end',
                        'digest_enabled',
                    ],
                ],
            ],
        ]);
    }
}
