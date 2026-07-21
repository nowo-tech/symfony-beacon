<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fresh installs must keep the setup wizard open until an admin finishes /setup.
 *
 * Version20260721200000 previously stamped setup_completed_at on every migrate,
 * including empty databases.
 */
final class Version20260721243000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clear setup_completed_at when no users exist (re-open setup wizard)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE instance_settings SET setup_completed_at = NULL WHERE id = 1 AND NOT EXISTS (SELECT 1 FROM app_user LIMIT 1)'
        );
    }

    public function down(Schema $schema): void
    {
        // No-op: cannot know whether the stamp was intentional.
    }
}
