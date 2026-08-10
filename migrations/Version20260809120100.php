<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename reserved MySQL column `system` → `is_system` on RBAC tables.
 */
final class Version20260809120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename instance_* .system columns to is_system (MySQL reserved word)';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['instance_permission'])) {
            $table = $sm->introspectTable('instance_permission');
            if ($table->hasColumn('system') && !$table->hasColumn('is_system')) {
                $this->addSql('ALTER TABLE instance_permission CHANGE `system` is_system TINYINT(1) DEFAULT 0 NOT NULL');
            }
        }
        if ($sm->tablesExist(['instance_role'])) {
            $table = $sm->introspectTable('instance_role');
            if ($table->hasColumn('system') && !$table->hasColumn('is_system')) {
                $this->addSql('ALTER TABLE instance_role CHANGE `system` is_system TINYINT(1) DEFAULT 0 NOT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['instance_permission'])) {
            $table = $sm->introspectTable('instance_permission');
            if ($table->hasColumn('is_system') && !$table->hasColumn('system')) {
                $this->addSql('ALTER TABLE instance_permission CHANGE is_system `system` TINYINT(1) DEFAULT 0 NOT NULL');
            }
        }
        if ($sm->tablesExist(['instance_role'])) {
            $table = $sm->introspectTable('instance_role');
            if ($table->hasColumn('is_system') && !$table->hasColumn('system')) {
                $this->addSql('ALTER TABLE instance_role CHANGE is_system `system` TINYINT(1) DEFAULT 0 NOT NULL');
            }
        }
    }
}
