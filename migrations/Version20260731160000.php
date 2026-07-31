<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

final class Version20260731160000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'project_read_token for JSON read API Bearer tokens (042)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'project_read_token' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'uuid', 'type' => 'string', 'length' => 36, 'notnull' => true],
                        ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'created_by_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'label', 'type' => 'string', 'length' => 120, 'notnull' => true],
                        ['name' => 'prefix', 'type' => 'string', 'length' => 16, 'notnull' => true],
                        ['name' => 'token_hash', 'type' => 'string', 'length' => 64, 'notnull' => true],
                        ['name' => 'active', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'revoked_at', 'type' => 'datetime_immutable', 'notnull' => false],
                        ['name' => 'last_used_at', 'type' => 'datetime_immutable', 'notnull' => false],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                    ],
                    MDK::INDEXES => [
                        ['name' => 'uniq_project_read_token_uuid', 'columns' => ['uuid'], 'unique' => true],
                        ['name' => 'uniq_project_read_token_hash', 'columns' => ['token_hash'], 'unique' => true],
                        ['name' => 'idx_project_read_token_project', 'columns' => ['project_id']],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::FOREIGN_KEYS => [
                        ['name' => 'fk_project_read_token_project', 'columns' => ['project_id'], 'foreign_table' => 'project', 'foreign_columns' => ['id'], 'on_delete' => 'CASCADE'],
                        ['name' => 'fk_project_read_token_user', 'columns' => ['created_by_id'], 'foreign_table' => 'app_user', 'foreign_columns' => ['id'], 'on_delete' => 'CASCADE'],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['project_read_token'],
        ]);
    }
}
