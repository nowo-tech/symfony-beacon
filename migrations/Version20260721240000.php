<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Member PWA push preferences and Web Push subscription storage.
 */
final class Version20260721240000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'app_user.push_notifications_enabled + push_subscription table for PWA Web Push';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $userTable = $sm->introspectTable('app_user');

        $tables = [];
        if (!$userTable->hasColumn('push_notifications_enabled')) {
            $tables['app_user'] = [
                MDK::COLUMNS => [
                    [
                        'name' => 'push_notifications_enabled',
                        'type' => 'boolean',
                        'notnull' => true,
                        'default' => false,
                    ],
                ],
            ];
        }

        if (!$sm->tablesExist(['push_subscription'])) {
            $tables['push_subscription'] = [
                MDK::COLUMNS => [
                    IdField::column(),
                    ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
                    ['name' => 'endpoint_hash', 'type' => 'string', 'length' => 64, 'notnull' => true],
                    ['name' => 'endpoint', 'type' => 'text', 'notnull' => true],
                    ['name' => 'p256dh', 'type' => 'text', 'notnull' => true],
                    ['name' => 'auth_token', 'type' => 'text', 'notnull' => true],
                    ['name' => 'content_encoding', 'type' => 'string', 'length' => 32, 'notnull' => true],
                    ['name' => 'user_agent', 'type' => 'string', 'length' => 255, 'notnull' => false],
                    ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                    ['name' => 'updated_at', 'type' => 'datetime_immutable', 'notnull' => true],
                ],
                MDK::PRIMARY_KEY => IdField::primaryKey(),
                MDK::INDEXES => [
                    ['name' => 'idx_push_subscription_user', 'columns' => ['user_id']],
                    ['name' => 'uniq_push_subscription_endpoint_hash', 'columns' => ['endpoint_hash'], 'unique' => true],
                ],
                MDK::FOREIGN_KEYS => [
                    [
                        'name' => 'fk_push_subscription_user',
                        'columns' => ['user_id'],
                        'foreign_table' => 'app_user',
                        'foreign_columns' => ['id'],
                        'onDelete' => MDK::ON_DELETE_CASCADE,
                    ],
                ],
            ];
        }

        if ([] !== $tables) {
            $this->applyMdk([
                MDK::TABLES => $tables,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => ['push_subscription'],
            MDK::TABLES => [
                'app_user' => [
                    MDK::DROP_COLUMNS => [
                        'push_notifications_enabled',
                    ],
                ],
            ],
        ]);
    }
}
