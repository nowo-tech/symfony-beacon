<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Inbound email webhook Message-ID deduplication (076).
 */
final class Version20260731210000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add inbound_email_message table for webhook Message-ID idempotency';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['inbound_email_message'])) {
            return;
        }

        $this->applyMdk([
            MDK::TABLES => [
                'inbound_email_message' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                        ['name' => 'message_id', 'type' => 'string', 'length' => 512, 'notnull' => true],
                        ['name' => 'comment_uuid', 'type' => 'string', 'length' => 36, 'notnull' => false],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['id']]],
                    MDK::INDEXES => [
                        ['columns' => ['message_id'], 'unique' => true, 'name' => 'uniq_inbound_email_message_id'],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['inbound_email_message'])) {
            $this->applyMdk([
                MDK::DROP_TABLES => ['inbound_email_message'],
            ]);
        }
    }
}
