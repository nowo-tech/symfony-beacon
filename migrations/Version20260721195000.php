<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use DoctrineMigrations\FieldDictionary\AuditFields;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * AuditKit blame (+ updated_at on groups) for admin users and groups.
 */
final class Version20260721195000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add AuditKit created/updated by on app_user and user_group; updated_at on user_group';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'app_user' => [
                    MDK::COLUMNS => [
                        // Align NOT NULL created_at with AuditKit TimestampableTrait (nullable).
                        AuditFields::createdAt(),
                        ...AuditFields::blameColumns(),
                    ],
                    MDK::INDEXES => [
                        ['columns' => ['created_by_id'], 'name' => 'IDX_APP_USER_CREATED_BY'],
                        ['columns' => ['updated_by_id'], 'name' => 'IDX_APP_USER_UPDATED_BY'],
                    ],
                    MDK::FOREIGN_KEYS => AuditFields::blameForeignKeys(
                        'app_user',
                        'FK_APP_USER_CREATED_BY',
                        'FK_APP_USER_UPDATED_BY',
                    ),
                ],
                'user_group' => [
                    MDK::COLUMNS => [
                        AuditFields::createdAt(),
                        AuditFields::updatedAt(),
                        ...AuditFields::blameColumns(),
                    ],
                    MDK::INDEXES => [
                        ['columns' => ['created_by_id'], 'name' => 'IDX_USER_GROUP_CREATED_BY'],
                        ['columns' => ['updated_by_id'], 'name' => 'IDX_USER_GROUP_UPDATED_BY'],
                    ],
                    MDK::FOREIGN_KEYS => AuditFields::blameForeignKeys(
                        'app_user',
                        'FK_USER_GROUP_CREATED_BY',
                        'FK_USER_GROUP_UPDATED_BY',
                    ),
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'user_group' => [
                    MDK::DROP_FOREIGN_KEYS => ['FK_USER_GROUP_CREATED_BY', 'FK_USER_GROUP_UPDATED_BY'],
                    MDK::DROP_INDEXES => ['IDX_USER_GROUP_CREATED_BY', 'IDX_USER_GROUP_UPDATED_BY'],
                    MDK::DROP_COLUMNS => ['updated_at', 'created_by_id', 'updated_by_id'],
                ],
                'app_user' => [
                    MDK::DROP_FOREIGN_KEYS => ['FK_APP_USER_CREATED_BY', 'FK_APP_USER_UPDATED_BY'],
                    MDK::DROP_INDEXES => ['IDX_APP_USER_CREATED_BY', 'IDX_APP_USER_UPDATED_BY'],
                    MDK::DROP_COLUMNS => ['created_by_id', 'updated_by_id'],
                ],
            ],
        ]);
    }
}
