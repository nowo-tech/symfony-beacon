<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260720104002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Breadcrumb Kit tables (dashboard_breadcrumb_*)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dashboard_breadcrumb_collection (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(64) NOT NULL, context_key VARCHAR(512) DEFAULT \'\' NOT NULL, name VARCHAR(255) DEFAULT NULL, home_icon VARCHAR(128) DEFAULT NULL, separator_icon VARCHAR(128) DEFAULT NULL, responsive_config JSON DEFAULT NULL, class_list VARCHAR(512) DEFAULT NULL, class_item VARCHAR(512) DEFAULT NULL, class_separator VARCHAR(512) DEFAULT NULL, class_current VARCHAR(512) DEFAULT NULL, inline_edit_access_key VARCHAR(64) DEFAULT NULL, UNIQUE INDEX uniq_breadcrumb_collection_code_context (code, context_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dashboard_breadcrumb_item (id INT AUTO_INCREMENT NOT NULL, route_name VARCHAR(255) NOT NULL, static_route_params JSON DEFAULT NULL, dynamic_param_keys JSON DEFAULT NULL, link_enabled TINYINT DEFAULT 1 NOT NULL, label VARCHAR(512) DEFAULT NULL, translations JSON DEFAULT NULL, icon VARCHAR(128) DEFAULT NULL, collection_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_A365DB8E514956FD (collection_id), INDEX IDX_A365DB8E727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE dashboard_breadcrumb_item ADD CONSTRAINT FK_A365DB8E514956FD FOREIGN KEY (collection_id) REFERENCES dashboard_breadcrumb_collection (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dashboard_breadcrumb_item ADD CONSTRAINT FK_A365DB8E727ACA70 FOREIGN KEY (parent_id) REFERENCES dashboard_breadcrumb_item (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_breadcrumb_item DROP FOREIGN KEY FK_A365DB8E514956FD');
        $this->addSql('ALTER TABLE dashboard_breadcrumb_item DROP FOREIGN KEY FK_A365DB8E727ACA70');
        $this->addSql('DROP TABLE dashboard_breadcrumb_item');
        $this->addSql('DROP TABLE dashboard_breadcrumb_collection');
    }
}
