<?php

declare(strict_types=1);

namespace DoctrineMigrations\FieldDictionary;

use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

/**
 * Applies an MDK definition through CreateTablesService from an AbstractMigration.
 *
 * Requires $this->connection and $this->addSql() from Doctrine\Migrations\AbstractMigration.
 */
trait AppliesMdkDefinition
{
    /**
     * @param array<string, mixed> $definition
     */
    private function applyMdk(array $definition): void
    {
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }
}
