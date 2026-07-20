<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720125200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create site_appearance for ROLE_ADMIN look & feel customization';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE site_appearance (id INT NOT NULL, brand_name VARCHAR(80) NOT NULL, brand_eyebrow VARCHAR(80) NOT NULL, accent_color VARCHAR(7) NOT NULL, accent_deep_color VARCHAR(7) NOT NULL, accent_color_dark VARCHAR(7) NOT NULL, accent_deep_color_dark VARCHAR(7) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO site_appearance (id, brand_name, brand_eyebrow, accent_color, accent_deep_color, accent_color_dark, accent_deep_color_dark) VALUES (1, 'symfony-beacon', 'Error tracking', '#1f6f54', '#134736', '#4aad7f', '#6bc49a')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_appearance');
    }
}
