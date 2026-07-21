<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Widen columns that store Halite ciphertext (+ ENC marker) for doctrine-encrypt-bundle.
 */
final class Version20260721100000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Widen project_api_key.secret_key and notification_destination.endpoint_url for field encryption';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'project_api_key' => [
                    MDK::COLUMNS => [
                        ['name' => 'secret_key', 'type' => 'text', 'notnull' => false],
                    ],
                ],
                'notification_destination' => [
                    MDK::COLUMNS => [
                        ['name' => 'endpoint_url', 'type' => 'text', 'notnull' => true],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'project_api_key' => [
                    MDK::COLUMNS => [
                        ['name' => 'secret_key', 'type' => 'string', 'length' => 64, 'notnull' => false],
                    ],
                ],
                'notification_destination' => [
                    MDK::COLUMNS => [
                        ['name' => 'endpoint_url', 'type' => 'string', 'length' => 2048, 'notnull' => true],
                    ],
                ],
            ],
        ]);
    }
}
