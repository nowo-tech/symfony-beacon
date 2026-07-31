<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * AuthKit social login: OAuth app credentials + linked user accounts.
 */
final class Version20260730120000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create auth_kit_social_credential and auth_kit_social_account tables';
    }

    public function up(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'auth_kit_social_credential' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'provider', 'type' => 'string', 'length' => 64, 'notnull' => true],
                        ['name' => 'label', 'type' => 'string', 'length' => 128, 'notnull' => true],
                        ['name' => 'client_id', 'type' => 'string', 'length' => 255, 'notnull' => true],
                        ['name' => 'client_secret', 'type' => 'text', 'notnull' => true],
                        ['name' => 'enabled', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'scopes', 'type' => 'json', 'notnull' => true],
                        ['name' => 'authorize_url', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'token_url', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'userinfo_url', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'updated_at', 'type' => 'datetime_immutable', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['provider'], 'unique' => true, 'name' => 'uniq_auth_kit_social_credential_provider'],
                    ],
                ],
                'auth_kit_social_account' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'provider', 'type' => 'string', 'length' => 64, 'notnull' => true],
                        ['name' => 'provider_user_id', 'type' => 'string', 'length' => 255, 'notnull' => true],
                        ['name' => 'user_class', 'type' => 'string', 'length' => 255, 'notnull' => true],
                        ['name' => 'user_id', 'type' => 'string', 'length' => 64, 'notnull' => true],
                        ['name' => 'user_identifier', 'type' => 'string', 'length' => 255, 'notnull' => true],
                        ['name' => 'access_token', 'type' => 'text', 'notnull' => false],
                        ['name' => 'refresh_token', 'type' => 'text', 'notnull' => false],
                        ['name' => 'token_expires_at', 'type' => 'datetime_immutable', 'notnull' => false],
                        ['name' => 'email', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'display_name', 'type' => 'string', 'length' => 255, 'notnull' => false],
                        ['name' => 'raw_profile', 'type' => 'json', 'notnull' => true],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'updated_at', 'type' => 'datetime_immutable', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        [
                            'columns' => ['provider', 'provider_user_id'],
                            'unique' => true,
                            'name' => 'uniq_auth_kit_social_account_provider_subject',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => [
                'auth_kit_social_account',
                'auth_kit_social_credential',
            ],
        ]);
    }
}
