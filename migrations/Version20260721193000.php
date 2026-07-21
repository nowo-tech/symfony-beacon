<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Instance settings singleton for encrypted Mailer DSN (and related operator config).
 */
final class Version20260721193000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create instance_settings for encrypted Mailer DSN and From address';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'instance_settings' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'mailer_dsn', 'type' => 'text', 'notnull' => false],
                        ['name' => 'mailer_from', 'type' => 'string', 'length' => 180, 'notnull' => false],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'updated_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'created_by_id', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'updated_by_id', 'type' => 'integer', 'notnull' => false],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['id']]],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['created_by_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_SET_NULL,
                            'name' => 'FK_INSTANCE_SETTINGS_CREATED_BY',
                        ],
                        [
                            'columns' => ['updated_by_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_SET_NULL,
                            'name' => 'FK_INSTANCE_SETTINGS_UPDATED_BY',
                        ],
                    ],
                ],
            ],
        ]);

        $this->addSql('INSERT INTO instance_settings (id, mailer_dsn, mailer_from, created_at, updated_at) VALUES (1, NULL, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['instance_settings'],
        ]);
    }
}
