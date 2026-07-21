<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use DoctrineMigrations\FieldDictionary\AuditFields;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

final class Version20260720233000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add notification_destination table for project outbound alerts';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'notification_destination' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'label', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'type', 'type' => 'string', 'length' => 20, 'notnull' => true],
                        ['name' => 'endpoint_url', 'type' => 'string', 'length' => 2048, 'notnull' => true],
                        ['name' => 'enabled', 'type' => 'boolean', 'notnull' => true],
                        ['name' => 'categories', 'type' => 'json', 'notnull' => true],
                        AuditFields::createdAt(true),
                        AuditFields::updatedAt(true),
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['project_id', 'enabled'], 'name' => 'idx_notif_dest_project_enabled'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['project_id'],
                            'foreign_table' => 'project',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_NOTIF_DEST_PROJECT',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['notification_destination'],
        ]);
    }
}
