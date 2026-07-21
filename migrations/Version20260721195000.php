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
 *
 * Idempotent: concurrent migrate (entrypoint + CLI) may have already added indexes.
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
        $sm = $this->connection->createSchemaManager();

        $appUser = $sm->introspectTable('app_user');
        $appUserDef = [
            MDK::COLUMNS => [
                AuditFields::createdAt(),
                ...AuditFields::blameColumns(),
            ],
        ];
        $appUserIndexes = [];
        if (!$appUser->hasIndex('IDX_APP_USER_CREATED_BY')) {
            $appUserIndexes[] = ['columns' => ['created_by_id'], 'name' => 'IDX_APP_USER_CREATED_BY'];
        }
        if (!$appUser->hasIndex('IDX_APP_USER_UPDATED_BY')) {
            $appUserIndexes[] = ['columns' => ['updated_by_id'], 'name' => 'IDX_APP_USER_UPDATED_BY'];
        }
        if ([] !== $appUserIndexes) {
            $appUserDef[MDK::INDEXES] = $appUserIndexes;
        }
        $appUserFks = array_map(static fn ($fk) => $fk->getName(), $appUser->getForeignKeys());
        $appUserFkDefs = [];
        if (!\in_array('FK_APP_USER_CREATED_BY', $appUserFks, true) || !\in_array('FK_APP_USER_UPDATED_BY', $appUserFks, true)) {
            foreach (AuditFields::blameForeignKeys('app_user', 'FK_APP_USER_CREATED_BY', 'FK_APP_USER_UPDATED_BY') as $fk) {
                if (!\in_array($fk['name'], $appUserFks, true)) {
                    $appUserFkDefs[] = $fk;
                }
            }
        }
        if ([] !== $appUserFkDefs) {
            $appUserDef[MDK::FOREIGN_KEYS] = $appUserFkDefs;
        }

        $userGroup = $sm->introspectTable('user_group');
        $userGroupDef = [
            MDK::COLUMNS => [
                AuditFields::createdAt(),
                AuditFields::updatedAt(),
                ...AuditFields::blameColumns(),
            ],
        ];
        $userGroupIndexes = [];
        if (!$userGroup->hasIndex('IDX_USER_GROUP_CREATED_BY')) {
            $userGroupIndexes[] = ['columns' => ['created_by_id'], 'name' => 'IDX_USER_GROUP_CREATED_BY'];
        }
        if (!$userGroup->hasIndex('IDX_USER_GROUP_UPDATED_BY')) {
            $userGroupIndexes[] = ['columns' => ['updated_by_id'], 'name' => 'IDX_USER_GROUP_UPDATED_BY'];
        }
        if ([] !== $userGroupIndexes) {
            $userGroupDef[MDK::INDEXES] = $userGroupIndexes;
        }
        $userGroupFks = array_map(static fn ($fk) => $fk->getName(), $userGroup->getForeignKeys());
        $userGroupFkDefs = [];
        if (!\in_array('FK_USER_GROUP_CREATED_BY', $userGroupFks, true) || !\in_array('FK_USER_GROUP_UPDATED_BY', $userGroupFks, true)) {
            foreach (AuditFields::blameForeignKeys('app_user', 'FK_USER_GROUP_CREATED_BY', 'FK_USER_GROUP_UPDATED_BY') as $fk) {
                if (!\in_array($fk['name'], $userGroupFks, true)) {
                    $userGroupFkDefs[] = $fk;
                }
            }
        }
        if ([] !== $userGroupFkDefs) {
            $userGroupDef[MDK::FOREIGN_KEYS] = $userGroupFkDefs;
        }

        $this->applyMdk([
            MDK::TABLES => [
                'app_user' => $appUserDef,
                'user_group' => $userGroupDef,
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
