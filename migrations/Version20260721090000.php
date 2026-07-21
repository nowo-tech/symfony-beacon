<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use DoctrineMigrations\FieldDictionary\AuditFields;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * UserKit (enabled / last_activity) + AuditKit columns on key entities.
 */
final class Version20260721090000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add user-kit and audit-kit columns for User, Project, SiteAppearance, NotificationDestination';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'app_user' => [
                    MDK::COLUMNS => [
                        ['name' => 'enabled', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'last_activity_at', 'type' => 'datetime_immutable', 'notnull' => false],
                        AuditFields::updatedAt(),
                    ],
                ],
                'project' => [
                    MDK::COLUMNS => [
                        AuditFields::updatedAt(),
                        ...AuditFields::blameColumns(),
                    ],
                    MDK::INDEXES => [
                        ['columns' => ['created_by_id'], 'name' => 'IDX_PROJECT_CREATED_BY'],
                        ['columns' => ['updated_by_id'], 'name' => 'IDX_PROJECT_UPDATED_BY'],
                    ],
                    MDK::FOREIGN_KEYS => AuditFields::blameForeignKeys(
                        'app_user',
                        'FK_PROJECT_CREATED_BY',
                        'FK_PROJECT_UPDATED_BY',
                    ),
                ],
                'notification_destination' => [
                    MDK::COLUMNS => AuditFields::blameColumns(),
                    MDK::INDEXES => [
                        ['columns' => ['created_by_id'], 'name' => 'IDX_NOTIF_DEST_CREATED_BY'],
                        ['columns' => ['updated_by_id'], 'name' => 'IDX_NOTIF_DEST_UPDATED_BY'],
                    ],
                    MDK::FOREIGN_KEYS => AuditFields::blameForeignKeys(
                        'app_user',
                        'FK_NOTIF_DEST_CREATED_BY',
                        'FK_NOTIF_DEST_UPDATED_BY',
                    ),
                ],
                'site_appearance' => [
                    MDK::COLUMNS => [
                        AuditFields::createdAt(),
                        AuditFields::updatedAt(),
                        ...AuditFields::blameColumns(),
                    ],
                    MDK::INDEXES => [
                        ['columns' => ['created_by_id'], 'name' => 'IDX_SITE_APPEARANCE_CREATED_BY'],
                        ['columns' => ['updated_by_id'], 'name' => 'IDX_SITE_APPEARANCE_UPDATED_BY'],
                    ],
                    MDK::FOREIGN_KEYS => AuditFields::blameForeignKeys(
                        'app_user',
                        'FK_SITE_APPEARANCE_CREATED_BY',
                        'FK_SITE_APPEARANCE_UPDATED_BY',
                    ),
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'site_appearance' => [
                    MDK::DROP_FOREIGN_KEYS => ['FK_SITE_APPEARANCE_CREATED_BY', 'FK_SITE_APPEARANCE_UPDATED_BY'],
                    MDK::DROP_INDEXES => ['IDX_SITE_APPEARANCE_CREATED_BY', 'IDX_SITE_APPEARANCE_UPDATED_BY'],
                    MDK::DROP_COLUMNS => ['created_at', 'updated_at', 'created_by_id', 'updated_by_id'],
                ],
                'notification_destination' => [
                    MDK::DROP_FOREIGN_KEYS => ['FK_NOTIF_DEST_CREATED_BY', 'FK_NOTIF_DEST_UPDATED_BY'],
                    MDK::DROP_INDEXES => ['IDX_NOTIF_DEST_CREATED_BY', 'IDX_NOTIF_DEST_UPDATED_BY'],
                    MDK::DROP_COLUMNS => ['created_by_id', 'updated_by_id'],
                ],
                'project' => [
                    MDK::DROP_FOREIGN_KEYS => ['FK_PROJECT_CREATED_BY', 'FK_PROJECT_UPDATED_BY'],
                    MDK::DROP_INDEXES => ['IDX_PROJECT_CREATED_BY', 'IDX_PROJECT_UPDATED_BY'],
                    MDK::DROP_COLUMNS => ['updated_at', 'created_by_id', 'updated_by_id'],
                ],
                'app_user' => [
                    MDK::DROP_COLUMNS => ['enabled', 'last_activity_at', 'updated_at'],
                ],
            ],
        ]);
    }
}
