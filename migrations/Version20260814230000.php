<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename reserved-safe app_user table to user (MySQL reserved word; quoted).
 */
final class Version20260814230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename table app_user to user';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['app_user']) && !$sm->tablesExist(['user'])) {
            $this->addSql('RENAME TABLE app_user TO `user`');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['user']) && !$sm->tablesExist(['app_user'])) {
            $this->addSql('RENAME TABLE `user` TO app_user');
        }
    }
}
