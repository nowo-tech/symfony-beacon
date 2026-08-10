<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-locale permission catalog labels (name/description translations JSON).
 */
final class Version20260810013000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add permission.name_translations and permission.description_translations JSON columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permission ADD name_translations JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE permission ADD description_translations JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql("UPDATE permission SET name_translations = JSON_OBJECT() WHERE name_translations IS NULL");
        $this->addSql("UPDATE permission SET description_translations = JSON_OBJECT() WHERE description_translations IS NULL");
        $this->addSql('ALTER TABLE permission CHANGE name_translations name_translations JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE permission CHANGE description_translations description_translations JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permission DROP name_translations');
        $this->addSql('ALTER TABLE permission DROP description_translations');
    }
}
