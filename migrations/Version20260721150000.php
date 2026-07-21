<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Shared login-throttle storage for multi-worker / multi-pod deployments.
 */
final class Version20260721150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create login_attempts table for nowo-tech/login-throttle-bundle database storage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE login_attempts (id INT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(45) NOT NULL, username VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', blocked TINYINT(1) DEFAULT 0 NOT NULL, INDEX idx_ip_username (ip_address, username), INDEX idx_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE login_attempts');
    }
}
