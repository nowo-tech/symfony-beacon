<?php

declare(strict_types=1);

namespace DoctrineMigrations\FieldDictionary;

use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Reusable MDK column / FK snippets aligned with nowo-tech/audit-kit-bundle
 * (created_by_id / updated_by_id) and common Beacon timestamps.
 */
final class AuditFields
{
    /**
     * @return array<string, mixed>
     */
    public static function createdAt(bool $notnull = false): array
    {
        return [
            'name' => 'created_at',
            'type' => 'datetime_immutable',
            'notnull' => $notnull,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function updatedAt(bool $notnull = false): array
    {
        return [
            'name' => 'updated_at',
            'type' => 'datetime_immutable',
            'notnull' => $notnull,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function timestampColumns(bool $notnull = false): array
    {
        return [self::createdAt($notnull), self::updatedAt($notnull)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function createdById(): array
    {
        return [
            'name' => 'created_by_id',
            'type' => 'integer',
            'notnull' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function updatedById(): array
    {
        return [
            'name' => 'updated_by_id',
            'type' => 'integer',
            'notnull' => false,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function blameColumns(): array
    {
        return [self::createdById(), self::updatedById()];
    }

    /**
     * @return array<string, mixed>
     */
    public static function createdByForeignKey(string $userTable = 'app_user', ?string $name = null): array
    {
        $fk = [
            'columns' => ['created_by_id'],
            'foreign_table' => $userTable,
            'foreign_columns' => ['id'],
            'onDelete' => MDK::ON_DELETE_SET_NULL,
        ];
        if (null !== $name) {
            $fk['name'] = $name;
        }

        return $fk;
    }

    /**
     * @return array<string, mixed>
     */
    public static function updatedByForeignKey(string $userTable = 'app_user', ?string $name = null): array
    {
        $fk = [
            'columns' => ['updated_by_id'],
            'foreign_table' => $userTable,
            'foreign_columns' => ['id'],
            'onDelete' => MDK::ON_DELETE_SET_NULL,
        ];
        if (null !== $name) {
            $fk['name'] = $name;
        }

        return $fk;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function blameForeignKeys(string $userTable = 'app_user', ?string $createdName = null, ?string $updatedName = null): array
    {
        return [
            self::createdByForeignKey($userTable, $createdName),
            self::updatedByForeignKey($userTable, $updatedName),
        ];
    }
}
