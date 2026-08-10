<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Extended instance ops defaults (envelope size, metrics token, inbound email, SSRF/resolve flags).
 */
final class Version20260810030000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'instance_settings envelope/metrics/inbound/SSRF ops defaults columns';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $table = $sm->introspectTable('instance_settings');
        $columns = [];

        if (!$table->hasColumn('envelope_max_bytes')) {
            $columns[] = ['name' => 'envelope_max_bytes', 'type' => 'integer', 'notnull' => true, 'default' => 2_097_152];
        }
        if (!$table->hasColumn('ingest_reject_query_auth')) {
            $columns[] = ['name' => 'ingest_reject_query_auth', 'type' => 'boolean', 'notnull' => true, 'default' => true];
        }
        if (!$table->hasColumn('metrics_token')) {
            $columns[] = ['name' => 'metrics_token', 'type' => 'text', 'notnull' => false];
        }
        if (!$table->hasColumn('metrics_require_token')) {
            $columns[] = ['name' => 'metrics_require_token', 'type' => 'boolean', 'notnull' => true, 'default' => false];
        }
        if (!$table->hasColumn('inbound_email_enabled')) {
            $columns[] = ['name' => 'inbound_email_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false];
        }
        if (!$table->hasColumn('inbound_mail_domain')) {
            $columns[] = ['name' => 'inbound_mail_domain', 'type' => 'string', 'length' => 255, 'notnull' => false];
        }
        if (!$table->hasColumn('inbound_webhook_secret')) {
            $columns[] = ['name' => 'inbound_webhook_secret', 'type' => 'text', 'notnull' => false];
        }
        if (!$table->hasColumn('allow_private_urls')) {
            $columns[] = ['name' => 'allow_private_urls', 'type' => 'boolean', 'notnull' => true, 'default' => false];
        }
        if (!$table->hasColumn('allow_anonymous_resolve')) {
            $columns[] = ['name' => 'allow_anonymous_resolve', 'type' => 'boolean', 'notnull' => true, 'default' => false];
        }

        if ([] === $columns) {
            return;
        }

        $this->applyMdk([
            MDK::TABLES => [
                'instance_settings' => [
                    MDK::COLUMNS => $columns,
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'instance_settings' => [
                    MDK::DROP_COLUMNS => [
                        'envelope_max_bytes',
                        'ingest_reject_query_auth',
                        'metrics_token',
                        'metrics_require_token',
                        'inbound_email_enabled',
                        'inbound_mail_domain',
                        'inbound_webhook_secret',
                        'allow_private_urls',
                        'allow_anonymous_resolve',
                    ],
                ],
            ],
        ]);
    }
}
