<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Rich event context: microsecond timestamps and promoted runtime/user columns.
 */
final class Version20260720200000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Add event context columns and DATETIME(6) precision for event timestamps';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'event' => [
                    MDK::COLUMNS => [
                        ['name' => 'php_version', 'type' => 'string', 'length' => 40, 'notnull' => false],
                        ['name' => 'symfony_version', 'type' => 'string', 'length' => 40, 'notnull' => false],
                        ['name' => 'user_identifier', 'type' => 'string', 'length' => 180, 'notnull' => false],
                    ],
                ],
            ],
        ]);

        // MDK does not reliably emit DATETIME(6) precision on MySQL column changes.
        $this->addSql('ALTER TABLE event CHANGE event_timestamp event_timestamp DATETIME(6) NOT NULL, CHANGE received_at received_at DATETIME(6) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event CHANGE event_timestamp event_timestamp DATETIME NOT NULL, CHANGE received_at received_at DATETIME NOT NULL');

        $this->applyMdk([
            MDK::TABLES => [
                'event' => [
                    MDK::DROP_COLUMNS => ['php_version', 'symfony_version', 'user_identifier'],
                ],
            ],
        ]);
    }
}
