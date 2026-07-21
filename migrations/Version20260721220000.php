<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Per-user product tour dismissal flag (057).
 */
final class Version20260721220000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'app_user.product_tour_seen_at for product tour (057)';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'app_user' => [
                    MDK::COLUMNS => [
                        ['name' => 'product_tour_seen_at', 'type' => 'datetime_immutable', 'notnull' => false],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'app_user' => [
                    MDK::DROP_COLUMNS => [
                        'product_tour_seen_at',
                    ],
                ],
            ],
        ]);
    }
}
