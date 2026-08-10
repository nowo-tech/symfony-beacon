<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fail-closed default: require a Prometheus metrics Bearer token for new installs.
 * Existing rows keep their current metrics_require_token value.
 */
final class Version20260810100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'instance_settings.metrics_require_token column default true (new rows)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE instance_settings CHANGE metrics_require_token metrics_require_token TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE instance_settings CHANGE metrics_require_token metrics_require_token TINYINT(1) DEFAULT 0 NOT NULL');
    }
}
