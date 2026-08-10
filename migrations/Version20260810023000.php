<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replace permission JSON locale maps with permission_translation Translatable rows.
 */
final class Version20260810023000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create permission_translation and drop permission.*_translations JSON columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE permission_translation (id INT AUTO_INCREMENT NOT NULL, permission_id INT NOT NULL, locale VARCHAR(8) NOT NULL, name VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, UNIQUE INDEX uniq_permission_translation_locale (permission_id, locale), INDEX IDX_PERM_TRANS_PERMISSION (permission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE permission_translation ADD CONSTRAINT FK_PERM_TRANS_PERMISSION FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE permission DROP name_translations');
        $this->addSql('ALTER TABLE permission DROP description_translations');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permission ADD name_translations JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE permission ADD description_translations JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql("UPDATE permission SET name_translations = JSON_OBJECT() WHERE name_translations IS NULL");
        $this->addSql("UPDATE permission SET description_translations = JSON_OBJECT() WHERE description_translations IS NULL");
        $this->addSql('ALTER TABLE permission CHANGE name_translations name_translations JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE permission CHANGE description_translations description_translations JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE permission_translation DROP FOREIGN KEY FK_PERM_TRANS_PERMISSION');
        $this->addSql('DROP TABLE permission_translation');
    }
}
