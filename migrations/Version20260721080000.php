<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use DoctrineMigrations\FieldDictionary\AuditFields;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Password policy: password_changed_at on app_user + password_history table.
 */
final class Version20260721080000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add password policy columns/history for nowo-tech/password-policy-bundle';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'app_user' => [
                    MDK::COLUMNS => [
                        ['name' => 'password_changed_at', 'type' => 'datetime_immutable', 'notnull' => false],
                    ],
                ],
                'password_history' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'password', 'type' => 'string', 'length' => 255, 'notnull' => true],
                        AuditFields::createdAt(true),
                        ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['user_id'], 'name' => 'idx_password_history_user'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['user_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_CASCADE,
                            'name' => 'FK_PASSWORD_HISTORY_USER',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['password_history'],
            MDK::TABLES => [
                'app_user' => [
                    MDK::DROP_COLUMNS => ['password_changed_at'],
                ],
            ],
        ]);
    }
}
