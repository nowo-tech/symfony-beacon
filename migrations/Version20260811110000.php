<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Project.portability code + membership active flag (089).
 */
final class Version20260811110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project.code (unique) and project_membership.active for config portability';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD code VARCHAR(120) NOT NULL DEFAULT \'\'');
        $this->addSql('UPDATE project SET code = slug WHERE code = \'\' OR code IS NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_project_code ON project (code)');
        $this->addSql('ALTER TABLE project_membership ADD active BOOLEAN NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_project_code ON project');
        $this->addSql('ALTER TABLE project DROP code');
        $this->addSql('ALTER TABLE project_membership DROP active');
    }
}
