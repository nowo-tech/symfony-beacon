<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Optional Slack user id on app_user for interactive Assign / Resolve attribution.
 */
final class Version20260731190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'app_user.slack_user_id for Slack interactive Assign-to-me';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $table = $sm->introspectTable('app_user');
        if (!$table->hasColumn('slack_user_id')) {
            $this->addSql('ALTER TABLE app_user ADD slack_user_id VARCHAR(64) DEFAULT NULL');
        }
        $indexes = $table->getIndexes();
        $hasUnique = false;
        foreach ($indexes as $index) {
            if ($index->isUnique() && ['slack_user_id'] === $index->getColumns()) {
                $hasUnique = true;
                break;
            }
        }
        if (!$hasUnique) {
            $this->addSql('CREATE UNIQUE INDEX uniq_app_user_slack_user_id ON app_user (slack_user_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $table = $sm->introspectTable('app_user');
        foreach ($table->getIndexes() as $index) {
            if ('uniq_app_user_slack_user_id' === $index->getName()) {
                $this->addSql('DROP INDEX uniq_app_user_slack_user_id ON app_user');
                break;
            }
        }
        if ($table->hasColumn('slack_user_id')) {
            $this->addSql('ALTER TABLE app_user DROP slack_user_id');
        }
    }
}
