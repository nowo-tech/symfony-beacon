<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * nowo-tech/http-log-bundle — HTTP request/response audit log table.
 */
final class Version20260803120000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create nowo_http_log_entry for HttpLogBundle';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['nowo_http_log_entry'])) {
            return;
        }

        $this->applyMdk([
            MDK::TABLES => [
                'nowo_http_log_entry' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                        ['name' => 'request_id', 'type' => 'string', 'length' => 64, 'notnull' => false],
                        ['name' => 'method', 'type' => 'string', 'length' => 16, 'notnull' => true],
                        ['name' => 'scheme', 'type' => 'string', 'length' => 16, 'notnull' => false],
                        ['name' => 'host', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'path', 'type' => 'string', 'length' => 2048, 'notnull' => true],
                        ['name' => 'route_name', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'query_params', 'type' => 'json', 'notnull' => false],
                        ['name' => 'status_code', 'type' => 'integer', 'notnull' => false],
                        ['name' => 'client_ip', 'type' => 'string', 'length' => 45, 'notnull' => false],
                        ['name' => 'content_type', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'body_content_type', 'type' => 'string', 'length' => 16, 'notnull' => false],
                        ['name' => 'duration_ms', 'type' => 'float', 'notnull' => false],
                        ['name' => 'user_identifier', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'request_headers', 'type' => 'json', 'notnull' => false],
                        ['name' => 'response_headers', 'type' => 'json', 'notnull' => false],
                        ['name' => 'request_body', 'type' => 'text', 'notnull' => false],
                        ['name' => 'response_body', 'type' => 'text', 'notnull' => false],
                        ['name' => 'request_body_truncated', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'response_body_truncated', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'response_body_stored', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['id']]],
                    MDK::INDEXES => [
                        ['columns' => ['request_id'], 'unique' => true, 'name' => 'UNIQ_A43F86E4427EB8A5'],
                        ['columns' => ['created_at'], 'name' => 'idx_http_log_created_at'],
                        ['columns' => ['route_name'], 'name' => 'idx_http_log_route_name'],
                        ['columns' => ['status_code'], 'name' => 'idx_http_log_status_code'],
                        ['columns' => ['client_ip'], 'name' => 'idx_http_log_client_ip'],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['nowo_http_log_entry'])) {
            $this->applyMdk([
                MDK::DROP_TABLES => ['nowo_http_log_entry'],
            ]);
        }
    }
}
