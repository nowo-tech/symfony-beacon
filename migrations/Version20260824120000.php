<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Device Intelligence persistence (prefixed tables; Device ID is not a credential).
 */
final class Version20260824120000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Create device_intelligence_device, observation, device_user, and device_trust tables';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['device_intelligence_device'])) {
            return;
        }

        $ulidPk = [['columns' => ['id']]];

        $this->applyMdk([
            MDK::TABLES => [
                'device_intelligence_device' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'string', 'length' => 26, 'notnull' => true],
                        ['name' => 'first_seen_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'last_seen_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'observation_count', 'type' => 'integer', 'notnull' => true, 'default' => 0],
                        ['name' => 'confidence', 'type' => 'float', 'notnull' => true, 'default' => 0.5],
                        ['name' => 'stability', 'type' => 'float', 'notnull' => true, 'default' => 0.5],
                        ['name' => 'status', 'type' => 'string', 'length' => 16, 'notnull' => true, 'default' => 'active'],
                        ['name' => 'os_family', 'type' => 'string', 'length' => 32, 'notnull' => true, 'default' => 'other'],
                        ['name' => 'browser_family', 'type' => 'string', 'length' => 32, 'notnull' => true, 'default' => 'other'],
                        ['name' => 'gpu_family', 'type' => 'string', 'length' => 64, 'notnull' => true, 'default' => 'other'],
                        ['name' => 'screen_class', 'type' => 'string', 'length' => 32, 'notnull' => true, 'default' => 'other'],
                        ['name' => 'timezone', 'type' => 'string', 'length' => 64, 'notnull' => true, 'default' => 'UTC'],
                        ['name' => 'blocking_key', 'type' => 'string', 'length' => 16, 'notnull' => true, 'default' => ''],
                        ['name' => 'label', 'type' => 'string', 'length' => 191, 'notnull' => true, 'default' => ''],
                        ['name' => 'metadata', 'type' => 'json', 'notnull' => true],
                        ['name' => 'last_signals', 'type' => 'json', 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => $ulidPk,
                    MDK::INDEXES => [
                        ['columns' => ['os_family', 'browser_family'], 'name' => 'idx_di_device_os_browser'],
                        ['columns' => ['last_seen_at'], 'name' => 'idx_di_device_last_seen'],
                    ],
                ],
                'device_intelligence_observation' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'string', 'length' => 26, 'notnull' => true],
                        ['name' => 'device_id', 'type' => 'string', 'length' => 26, 'notnull' => true],
                        ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'schema_version', 'type' => 'integer', 'notnull' => true, 'default' => 1],
                        ['name' => 'sdk_version', 'type' => 'string', 'length' => 32, 'notnull' => false],
                        ['name' => 'ip_hash', 'type' => 'string', 'length' => 64, 'notnull' => false],
                        ['name' => 'country', 'type' => 'string', 'length' => 8, 'notnull' => false],
                        ['name' => 'user_agent_family', 'type' => 'string', 'length' => 64, 'notnull' => false],
                        ['name' => 'raw_user_agent', 'type' => 'string', 'length' => 512, 'notnull' => false],
                        ['name' => 'session_identifier', 'type' => 'string', 'length' => 128, 'notnull' => false],
                        ['name' => 'user_identifier', 'type' => 'string', 'length' => 191, 'notnull' => false],
                        ['name' => 'signals', 'type' => 'json', 'notnull' => true],
                        ['name' => 'risk_score', 'type' => 'integer', 'notnull' => true, 'default' => 0],
                        ['name' => 'degraded', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                        ['name' => 'enhancement_level', 'type' => 'integer', 'notnull' => true, 'default' => 0],
                    ],
                    MDK::PRIMARY_KEY => $ulidPk,
                    MDK::INDEXES => [
                        ['columns' => ['device_id', 'created_at'], 'name' => 'idx_di_obs_device_created'],
                    ],
                ],
                'device_intelligence_device_user' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'device_id', 'type' => 'string', 'length' => 26, 'notnull' => true],
                        ['name' => 'user_identifier', 'type' => 'string', 'length' => 191, 'notnull' => true],
                        ['name' => 'first_seen_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'last_seen_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'login_count', 'type' => 'integer', 'notnull' => true, 'default' => 0],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['device_id', 'user_identifier'], 'unique' => true, 'name' => 'uniq_di_device_user'],
                        ['columns' => ['user_identifier'], 'name' => 'idx_di_device_user_user'],
                    ],
                ],
                'device_intelligence_device_trust' => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'device_id', 'type' => 'string', 'length' => 26, 'notnull' => true],
                        ['name' => 'user_identifier', 'type' => 'string', 'length' => 191, 'notnull' => true],
                        ['name' => 'trusted_at', 'type' => 'datetime_immutable', 'notnull' => true],
                        ['name' => 'expires_at', 'type' => 'datetime_immutable', 'notnull' => false],
                        ['name' => 'revoked_at', 'type' => 'datetime_immutable', 'notnull' => false],
                        ['name' => 'label', 'type' => 'string', 'length' => 191, 'notnull' => true, 'default' => ''],
                        ['name' => 'granted_by', 'type' => 'string', 'length' => 32, 'notnull' => true, 'default' => 'user'],
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                    MDK::INDEXES => [
                        ['columns' => ['device_id', 'user_identifier'], 'unique' => true, 'name' => 'uniq_di_device_trust'],
                        ['columns' => ['user_identifier'], 'name' => 'idx_di_device_trust_user'],
                    ],
                ],
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::DROP_TABLES => [
                'device_intelligence_device_trust',
                'device_intelligence_device_user',
                'device_intelligence_observation',
                'device_intelligence_device',
            ],
        ]);
    }
}
