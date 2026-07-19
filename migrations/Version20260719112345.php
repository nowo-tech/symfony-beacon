<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719112345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(120) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE daily_project_stat (id INT AUTO_INCREMENT NOT NULL, stat_date DATE NOT NULL, error_count INT NOT NULL, transaction_count INT NOT NULL, n_plus_one_count INT NOT NULL, project_id INT NOT NULL, INDEX IDX_94F57C1F166D1F9C (project_id), UNIQUE INDEX uniq_project_stat_day (project_id, stat_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, event_id VARCHAR(64) NOT NULL, environment VARCHAR(80) DEFAULT NULL, release_version VARCHAR(120) DEFAULT NULL, platform VARCHAR(40) NOT NULL, payload JSON NOT NULL, event_timestamp DATETIME NOT NULL, received_at DATETIME NOT NULL, issue_id INT NOT NULL, INDEX IDX_3BAE0AA75E7AA58C (issue_id), INDEX idx_event_issue_received (issue_id, received_at), UNIQUE INDEX uniq_event_id (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE issue (id INT AUTO_INCREMENT NOT NULL, fingerprint VARCHAR(64) NOT NULL, title VARCHAR(500) NOT NULL, culprit VARCHAR(40) NOT NULL, level VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, event_count INT NOT NULL, first_seen DATETIME NOT NULL, last_seen DATETIME NOT NULL, project_id INT NOT NULL, INDEX IDX_12AD233E166D1F9C (project_id), INDEX idx_issue_project_last_seen (project_id, last_seen), INDEX idx_issue_project_status (project_id, status), UNIQUE INDEX uniq_project_fingerprint (project_id, fingerprint), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE perf_span (id INT AUTO_INCREMENT NOT NULL, span_id VARCHAR(32) NOT NULL, op VARCHAR(80) NOT NULL, description VARCHAR(500) NOT NULL, duration_ms DOUBLE PRECISION NOT NULL, n_plus_one_candidate TINYINT NOT NULL, transaction_id INT NOT NULL, INDEX IDX_6A2F7D602FC0CB0F (transaction_id), INDEX idx_span_tx_op (transaction_id, op), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE perf_transaction (id INT AUTO_INCREMENT NOT NULL, event_id VARCHAR(64) NOT NULL, transaction_name VARCHAR(200) NOT NULL, duration_ms DOUBLE PRECISION NOT NULL, span_count INT NOT NULL, n_plus_one_count INT NOT NULL, payload JSON NOT NULL, received_at DATETIME NOT NULL, project_id INT NOT NULL, INDEX IDX_B02C000F166D1F9C (project_id), INDEX idx_tx_project_received (project_id, received_at), INDEX idx_tx_nplus1 (project_id, n_plus_one_count), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_project_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_api_key (id INT AUTO_INCREMENT NOT NULL, public_key VARCHAR(64) NOT NULL, secret_key VARCHAR(64) DEFAULT NULL, label VARCHAR(80) NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, project_id INT NOT NULL, INDEX IDX_5F0CE1F8166D1F9C (project_id), UNIQUE INDEX uniq_api_key_public (public_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_membership (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_9E59A9B7166D1F9C (project_id), INDEX IDX_9E59A9B7A76ED395 (user_id), UNIQUE INDEX uniq_project_user (project_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE daily_project_stat ADD CONSTRAINT FK_94F57C1F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA75E7AA58C FOREIGN KEY (issue_id) REFERENCES issue (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE issue ADD CONSTRAINT FK_12AD233E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE perf_span ADD CONSTRAINT FK_6A2F7D602FC0CB0F FOREIGN KEY (transaction_id) REFERENCES perf_transaction (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE perf_transaction ADD CONSTRAINT FK_B02C000F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_api_key ADD CONSTRAINT FK_5F0CE1F8166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_membership ADD CONSTRAINT FK_9E59A9B7166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_membership ADD CONSTRAINT FK_9E59A9B7A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE daily_project_stat DROP FOREIGN KEY FK_94F57C1F166D1F9C');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA75E7AA58C');
        $this->addSql('ALTER TABLE issue DROP FOREIGN KEY FK_12AD233E166D1F9C');
        $this->addSql('ALTER TABLE perf_span DROP FOREIGN KEY FK_6A2F7D602FC0CB0F');
        $this->addSql('ALTER TABLE perf_transaction DROP FOREIGN KEY FK_B02C000F166D1F9C');
        $this->addSql('ALTER TABLE project_api_key DROP FOREIGN KEY FK_5F0CE1F8166D1F9C');
        $this->addSql('ALTER TABLE project_membership DROP FOREIGN KEY FK_9E59A9B7166D1F9C');
        $this->addSql('ALTER TABLE project_membership DROP FOREIGN KEY FK_9E59A9B7A76ED395');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE daily_project_stat');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE issue');
        $this->addSql('DROP TABLE perf_span');
        $this->addSql('DROP TABLE perf_transaction');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE project_api_key');
        $this->addSql('DROP TABLE project_membership');
    }
}
