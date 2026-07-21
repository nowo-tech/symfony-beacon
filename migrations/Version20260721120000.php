<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use Symfony\Component\Uid\Uuid;

/**
 * Public UUID columns for UI route identifiers (integer PKs remain internal).
 */
final class Version20260721120000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    private const TABLES = [
        'project',
        'issue',
        'perf_transaction',
        'notification_destination',
        'app_user',
    ];

    public function getDescription(): string
    {
        return 'Add public uuid columns for Project, Issue, PerfTransaction, NotificationDestination, and User';
    }

    public function up(Schema $schema): void
    {
        $tables = [];
        foreach (self::TABLES as $table) {
            $tables[$table] = [
                MDK::COLUMNS => [
                    ['name' => 'uuid', 'type' => 'string', 'length' => 36, 'notnull' => false],
                ],
            ];
        }

        $this->applyMdk([
            MDK::TABLES => $tables,
        ]);
    }

    public function postUp(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $ids = $this->connection->fetchFirstColumn(
                \sprintf('SELECT id FROM %s WHERE uuid IS NULL OR uuid = \'\'', $table)
            );
            foreach ($ids as $id) {
                $this->connection->update(
                    $table,
                    ['uuid' => Uuid::v7()->toRfc4122()],
                    ['id' => $id],
                );
            }
        }

        $tables = [];
        foreach (self::TABLES as $table) {
            $tables[$table] = [
                MDK::COLUMNS => [
                    ['name' => 'uuid', 'type' => 'string', 'length' => 36, 'notnull' => true],
                ],
                MDK::INDEXES => [
                    ['columns' => ['uuid'], 'unique' => true, 'name' => 'uniq_'.$table.'_uuid'],
                ],
            ];
        }

        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        foreach ($service->apply($introspected, [MDK::TABLES => $tables]) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $tables = [];
        foreach (self::TABLES as $table) {
            $tables[$table] = [
                MDK::DROP_INDEXES => ['uniq_'.$table.'_uuid'],
                MDK::DROP_COLUMNS => ['uuid'],
            ];
        }

        $this->applyMdk([
            MDK::TABLES => $tables,
        ]);
    }
}
