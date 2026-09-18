<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Operator-editable legal pages (one row per slug and locale).
 */
final class Version20260918160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add legal_document for admin-edited notice, privacy, terms, and cookies';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('legal_document')) {
            return;
        }

        $this->addSql('CREATE TABLE legal_document (
            id INT AUTO_INCREMENT NOT NULL,
            slug VARCHAR(32) NOT NULL,
            locale VARCHAR(8) NOT NULL,
            title VARCHAR(180) NOT NULL,
            body LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_by_id INT DEFAULT NULL,
            updated_by_id INT DEFAULT NULL,
            UNIQUE INDEX uniq_legal_document_slug_locale (slug, locale),
            INDEX IDX_legal_document_created_by (created_by_id),
            INDEX IDX_legal_document_updated_by (updated_by_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_legal_document_created_by FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL,
            CONSTRAINT FK_legal_document_updated_by FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('legal_document')) {
            $this->addSql('DROP TABLE legal_document');
        }
    }
}
