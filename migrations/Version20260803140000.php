<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Issue @mention inbox rows for dashboard Mentions panel (079+/panel aside).
 */
final class Version20260803140000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create issue_mention for dashboard Mentions inbox';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['issue_mention'])) {
            return;
        }

        $this->applyMdk([
            MDK::TABLES => [
                'issue_mention' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                        ['name' => 'comment_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'mentioned_user_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'read_at', 'type' => 'datetime_immutable', 'notnull' => false],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['id']]],
                    MDK::INDEXES => [
                        ['columns' => ['comment_id', 'mentioned_user_id'], 'unique' => true, 'name' => 'uniq_issue_mention_comment_user'],
                        ['columns' => ['mentioned_user_id', 'created_at'], 'name' => 'idx_issue_mention_user_created'],
                        ['columns' => ['mentioned_user_id', 'read_at'], 'name' => 'idx_issue_mention_user_unread'],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns' => ['comment_id'],
                            'foreign_table' => 'issue_comment',
                            'foreign_columns' => ['id'],
                            'on_delete' => 'CASCADE',
                            'name' => 'fk_issue_mention_comment',
                        ],
                        [
                            'columns' => ['mentioned_user_id'],
                            'foreign_table' => 'app_user',
                            'foreign_columns' => ['id'],
                            'on_delete' => 'CASCADE',
                            'name' => 'fk_issue_mention_user',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['issue_mention'])) {
            $this->applyMdk([
                MDK::DROP_TABLES => ['issue_mention'],
            ]);
        }
    }
}
