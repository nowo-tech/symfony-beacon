<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Optional Mercure instance settings (admin-controlled live alerts).
 */
final class Version20260721241000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'instance_settings Mercure enable + URL/secret overrides';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $table = $sm->introspectTable('instance_settings');
        $columns = [];

        if (!$table->hasColumn('mercure_enabled')) {
            $columns[] = [
                'name' => 'mercure_enabled',
                'type' => 'boolean',
                'notnull' => true,
                'default' => false,
            ];
        }
        if (!$table->hasColumn('mercure_url')) {
            $columns[] = ['name' => 'mercure_url', 'type' => 'text', 'notnull' => false];
        }
        if (!$table->hasColumn('mercure_public_url')) {
            $columns[] = ['name' => 'mercure_public_url', 'type' => 'text', 'notnull' => false];
        }
        if (!$table->hasColumn('mercure_jwt_secret')) {
            $columns[] = ['name' => 'mercure_jwt_secret', 'type' => 'text', 'notnull' => false];
        }

        if ([] === $columns) {
            return;
        }

        $this->applyMdk([
            MDK::TABLES => [
                'instance_settings' => [
                    MDK::COLUMNS => $columns,
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'instance_settings' => [
                    MDK::DROP_COLUMNS => [
                        'mercure_enabled',
                        'mercure_url',
                        'mercure_public_url',
                        'mercure_jwt_secret',
                    ],
                ],
            ],
        ]);
    }
}
