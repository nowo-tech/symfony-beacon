<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720134500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user preferred_locale and preferred_theme for account preferences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD preferred_locale VARCHAR(8) DEFAULT NULL, ADD preferred_theme VARCHAR(8) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP preferred_locale, DROP preferred_theme');
    }
}
