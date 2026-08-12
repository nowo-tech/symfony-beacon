<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Unify Instance RBAC table names to shared vocabulary:
 * permission, role, role_permission, role_user.
 *
 * Historical create migrations used instance_* prefixes; this renames in place.
 */
final class Version20260809160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename instance_* RBAC tables to shared permission/role/role_permission/role_user';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        $permissionIndexes = null;
        if ($sm->tablesExist(['instance_permission']) && !$sm->tablesExist(['permission'])) {
            $permissionIndexes = $sm->listTableIndexes('instance_permission');
            $this->addSql('RENAME TABLE instance_permission TO permission');
        } elseif ($sm->tablesExist(['permission'])) {
            $permissionIndexes = $sm->listTableIndexes('permission');
        }
        if (null !== $permissionIndexes) {
            $this->queueIndexRenames('permission', $permissionIndexes, [
                'uniq_instance_permission_key' => 'uniq_permission_key',
                'uniq_instance_permission_uuid' => 'uniq_permission_uuid',
                'idx_instance_permission_category' => 'idx_permission_category',
            ]);
        }

        $roleIndexes = null;
        if ($sm->tablesExist(['instance_role']) && !$sm->tablesExist(['role'])) {
            $roleIndexes = $sm->listTableIndexes('instance_role');
            $this->addSql('RENAME TABLE instance_role TO `role`');
        } elseif ($sm->tablesExist(['role'])) {
            $roleIndexes = $sm->listTableIndexes('role');
        }
        if (null !== $roleIndexes) {
            $this->queueIndexRenames('role', $roleIndexes, [
                'uniq_instance_role_code' => 'uniq_role_code',
                'uniq_instance_role_uuid' => 'uniq_role_uuid',
            ]);
        }

        if ($sm->tablesExist(['instance_role_permission']) && !$sm->tablesExist(['role_permission'])) {
            $this->addSql('RENAME TABLE instance_role_permission TO role_permission');
        }
        if ($sm->tablesExist(['instance_user_role']) && !$sm->tablesExist(['role_user'])) {
            $this->addSql('RENAME TABLE instance_user_role TO role_user');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['role_user']) && !$sm->tablesExist(['instance_user_role'])) {
            $this->addSql('RENAME TABLE role_user TO instance_user_role');
        }
        if ($sm->tablesExist(['role_permission']) && !$sm->tablesExist(['instance_role_permission'])) {
            $this->addSql('RENAME TABLE role_permission TO instance_role_permission');
        }

        if ($sm->tablesExist(['role']) && !$sm->tablesExist(['instance_role'])) {
            $indexes = $sm->listTableIndexes('role');
            $this->queueIndexRenames('role', $indexes, [
                'uniq_role_code' => 'uniq_instance_role_code',
                'uniq_role_uuid' => 'uniq_instance_role_uuid',
            ]);
            $this->addSql('RENAME TABLE `role` TO instance_role');
        }

        if ($sm->tablesExist(['permission']) && !$sm->tablesExist(['instance_permission'])) {
            $indexes = $sm->listTableIndexes('permission');
            $this->queueIndexRenames('permission', $indexes, [
                'uniq_permission_key' => 'uniq_instance_permission_key',
                'uniq_permission_uuid' => 'uniq_instance_permission_uuid',
                'idx_permission_category' => 'idx_instance_permission_category',
            ]);
            $this->addSql('RENAME TABLE permission TO instance_permission');
        }
    }

    /**
     * @param array<string, mixed>  $indexes
     * @param array<string, string> $fromTo
     */
    private function queueIndexRenames(string $table, array $indexes, array $fromTo): void
    {
        foreach ($fromTo as $from => $to) {
            if (!isset($indexes[$from]) || isset($indexes[$to])) {
                continue;
            }
            $this->addSql(\sprintf(
                'ALTER TABLE `%s` RENAME INDEX `%s` TO `%s`',
                $table,
                $from,
                $to,
            ));
        }
    }
}
