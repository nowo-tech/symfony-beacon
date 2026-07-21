<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * First-run setup wizard completion flag on instance_settings.
 */
final class Version20260721200000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'instance_settings.setup_completed_at for setup wizard (056)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'instance_settings' => [
                    MDK::COLUMNS => [
                        ['name' => 'setup_completed_at', 'type' => 'datetime_immutable', 'notnull' => false],
                    ],
                ],
            ],
        ]);

        // Existing installs: do not force the wizard after upgrade.
        $this->addSql('UPDATE instance_settings SET setup_completed_at = CURRENT_TIMESTAMP WHERE id = 1 AND setup_completed_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'instance_settings' => [
                    MDK::DROP_COLUMNS => [
                        'setup_completed_at',
                    ],
                ],
            ],
        ]);
    }
}
