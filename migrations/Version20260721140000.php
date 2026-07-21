<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Create `user_action` for the admin / membership activity timeline.
 *
 * Stores actor, subject user, JSON context, optional client IP, and created_at.
 */
final class Version20260721140000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create user_action table for admin and membership activity history';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'user_action' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'action', 'type' => 'string', 'length' => 48, 'notnull' => true],
                        ['name' => 'context', 'type' => 'json', 'notnull' => true],
                        ['name' => 'ip_address', 'type' => 'string', 'length' => 45, 'notnull' => false],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'actor_id', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'subject_user_id', 'type' => 'integer', 'notnull' => false],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['subject_user_id', 'created_at'], 'name' => 'idx_user_action_subject_created'],
                        ['columns' => ['actor_id', 'created_at'], 'name' => 'idx_user_action_actor_created'],
                        ['columns' => ['created_at'], 'name' => 'idx_user_action_created'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['actor_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_SET_NULL,
                            'name' => 'FK_USER_ACTION_ACTOR',
                        ],
                        [
                            'columns' => ['subject_user_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'onDelete' => MDK::ON_DELETE_SET_NULL,
                            'name' => 'FK_USER_ACTION_SUBJECT',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['user_action'],
        ]);
    }
}
