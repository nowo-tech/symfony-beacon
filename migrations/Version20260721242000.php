<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Widen mailer_from for Halite ciphertext; Mailer From + Mercure URL fields use #[Encrypted].
 */
final class Version20260721242000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Widen instance_settings.mailer_from to text for encrypted Mailer/Mercure settings';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'instance_settings' => [
                    MDK::COLUMNS => [
                        ['name' => 'mailer_from', 'type' => 'text', 'notnull' => false],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'instance_settings' => [
                    MDK::COLUMNS => [
                        ['name' => 'mailer_from', 'type' => 'string', 'length' => 180, 'notnull' => false],
                    ],
                ],
            ],
        ]);
    }
}
