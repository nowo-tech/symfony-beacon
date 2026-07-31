<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * AuthKit 1.12: QR login challenge table, enterprise SSO flag, user phone for QR approve.
 */
final class Version20260731200000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'AuthKit 1.12 QR challenge table, enterprise_sso column, app_user phone fields';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if (!$sm->tablesExist(['auth_kit_qr_login_challenge'])) {
            $this->applyMdk([
                MDK::TABLES => [
                    'auth_kit_qr_login_challenge' => [
                        MDK::COLUMNS => [
                            ['name' => 'id', 'type' => 'string', 'length' => 36, 'notnull' => true],
                            ['name' => 'public_code', 'type' => 'string', 'length' => 8, 'notnull' => true],
                            ['name' => 'status', 'type' => 'string', 'length' => 16, 'notnull' => true],
                            ['name' => 'user_class', 'type' => 'string', 'length' => 255, 'notnull' => false],
                            ['name' => 'user_id', 'type' => 'string', 'length' => 64, 'notnull' => false],
                            ['name' => 'phone_hint', 'type' => 'string', 'length' => 32, 'notnull' => false],
                            ['name' => 'desktop_cookie_hash', 'type' => 'string', 'length' => 64, 'notnull' => true],
                            ['name' => 'desktop_ip_hash', 'type' => 'string', 'length' => 64, 'notnull' => true],
                            ['name' => 'desktop_ua_hash', 'type' => 'string', 'length' => 64, 'notnull' => true],
                            ['name' => 'desktop_ua_label', 'type' => 'string', 'length' => 128, 'notnull' => true],
                            ['name' => 'approve_token_hash', 'type' => 'string', 'length' => 64, 'notnull' => true],
                            ['name' => 'approve_token_used_at', 'type' => 'datetime_immutable', 'notnull' => false],
                            ['name' => 'expires_at', 'type' => 'datetime_immutable', 'notnull' => true],
                            ['name' => 'approved_at', 'type' => 'datetime_immutable', 'notnull' => false],
                            ['name' => 'consumed_at', 'type' => 'datetime_immutable', 'notnull' => false],
                            ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                            ['name' => 'updated_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ],
                        MDK::PRIMARY_KEY => [['columns' => ['id']]],
                        MDK::INDEXES => [
                            ['columns' => ['public_code'], 'unique' => true, 'name' => 'uniq_auth_kit_qr_login_public_code'],
                            ['columns' => ['status', 'expires_at'], 'unique' => false, 'name' => 'idx_qr_challenge_status_expires'],
                        ],
                    ],
                ],
            ]);
        }

        if ($sm->tablesExist(['auth_kit_social_credential'])) {
            $cred = $sm->introspectTable('auth_kit_social_credential');
            if (!$cred->hasColumn('enterprise_sso')) {
                $this->addSql('ALTER TABLE auth_kit_social_credential ADD enterprise_sso TINYINT(1) DEFAULT 0 NOT NULL');
            }
        }

        $user = $sm->introspectTable('app_user');
        if (!$user->hasColumn('phone')) {
            $this->addSql('ALTER TABLE app_user ADD phone VARCHAR(32) DEFAULT NULL');
        }
        if (!$user->hasColumn('phone_verified_at')) {
            $this->addSql('ALTER TABLE app_user ADD phone_verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        $user = $sm->introspectTable('app_user');
        if ($user->hasColumn('phone_verified_at')) {
            $this->addSql('ALTER TABLE app_user DROP phone_verified_at');
        }
        if ($user->hasColumn('phone')) {
            $this->addSql('ALTER TABLE app_user DROP phone');
        }

        if ($sm->tablesExist(['auth_kit_social_credential'])) {
            $cred = $sm->introspectTable('auth_kit_social_credential');
            if ($cred->hasColumn('enterprise_sso')) {
                $this->addSql('ALTER TABLE auth_kit_social_credential DROP enterprise_sso');
            }
        }

        if ($sm->tablesExist(['auth_kit_qr_login_challenge'])) {
            $this->applyMdk([
                MDK::DROP_TABLES => ['auth_kit_qr_login_challenge'],
            ]);
        }
    }
}
