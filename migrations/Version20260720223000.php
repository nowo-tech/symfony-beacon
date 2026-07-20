<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * User preference: which issue detail panels start collapsed.
 */
final class Version20260720223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add app_user.preferred_collapsed_issue_panels JSON column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD preferred_collapsed_issue_panels JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP preferred_collapsed_issue_panels');
    }
}
