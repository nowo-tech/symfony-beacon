<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AppliesMdkDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Event tenancy: project_id + unique (project_id, event_id), filter indexes, normalize issue.level.
 *
 * Idempotent: a prior partial run may have added nullable project_id and dropped uniq_event_id.
 */
final class Version20260721230000 extends AbstractMigration
{
    use AppliesMdkDefinition;

    public function getDescription(): string
    {
        return 'Scope event.event_id per project; add env/release indexes; whitelist issue.level';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $eventTable = $sm->introspectTable('event');

        if (!$eventTable->hasColumn('project_id')) {
            $this->applyMdk([
                MDK::TABLES => [
                    'event' => [
                        MDK::COLUMNS => [
                            ['name' => 'project_id', 'type' => 'integer', 'notnull' => false],
                        ],
                    ],
                ],
            ]);
        }

        $this->addSql('UPDATE event SET project_id = (SELECT project_id FROM issue WHERE issue.id = event.issue_id) WHERE project_id IS NULL');
        $this->addSql('DELETE FROM event WHERE project_id IS NULL');

        // Re-introspect: adding project_id may have changed indexes (MDK/MySQL).
        $eventTable = $sm->introspectTable('event');

        // Drop legacy global unique if still present (name varies by platform).
        foreach ($eventTable->getIndexes() as $index) {
            $isLegacyEventIdUnique = 'uniq_event_id' === $index->getName()
                || (['event_id'] === $index->getColumns() && $index->isUnique() && !$index->isPrimary());
            if (!$isLegacyEventIdUnique) {
                continue;
            }
            if (!$eventTable->hasIndex($index->getName())) {
                break;
            }
            $this->applyMdk([
                MDK::TABLES => [
                    'event' => [
                        MDK::DROP_INDEXES => [$index->getName()],
                    ],
                ],
            ]);
            break;
        }

        // Refresh after possible DROP.
        $eventTable = $sm->introspectTable('event');

        $indexNames = array_map(static fn ($i) => $i->getName(), $eventTable->getIndexes());
        $indexesToAdd = [];
        if (!\in_array('uniq_project_event_id', $indexNames, true)) {
            $indexesToAdd[] = ['columns' => ['project_id', 'event_id'], 'unique' => true, 'name' => 'uniq_project_event_id'];
        }
        if (!\in_array('idx_event_issue_environment', $indexNames, true)) {
            $indexesToAdd[] = ['columns' => ['issue_id', 'environment'], 'name' => 'idx_event_issue_environment'];
        }
        if (!\in_array('idx_event_issue_release', $indexNames, true)) {
            $indexesToAdd[] = ['columns' => ['issue_id', 'release_version'], 'name' => 'idx_event_issue_release'];
        }

        $fkNames = array_map(static fn ($fk) => $fk->getName(), $eventTable->getForeignKeys());
        $foreignKeys = [];
        if (!\in_array('FK_EVENT_PROJECT', $fkNames, true)) {
            $foreignKeys[] = [
                'columns' => ['project_id'],
                'foreign_table' => 'project',
                'foreign_columns' => ['id'],
                'onDelete' => MDK::ON_DELETE_CASCADE,
                'name' => 'FK_EVENT_PROJECT',
            ];
        }

        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'project_id', 'type' => 'integer', 'notnull' => true],
            ],
        ];
        if ([] !== $indexesToAdd) {
            $tableDef[MDK::INDEXES] = $indexesToAdd;
        }
        if ([] !== $foreignKeys) {
            $tableDef[MDK::FOREIGN_KEYS] = $foreignKeys;
        }

        $this->applyMdk([
            MDK::TABLES => [
                'event' => $tableDef,
            ],
        ]);

        $this->addSql("UPDATE issue SET level = 'error' WHERE level NOT IN ('fatal', 'error', 'warning', 'info', 'debug')");
    }

    public function down(Schema $schema): void
    {
        $this->applyMdk([
            MDK::TABLES => [
                'event' => [
                    MDK::DROP_FOREIGN_KEYS => ['FK_EVENT_PROJECT'],
                    MDK::DROP_INDEXES => [
                        'uniq_project_event_id',
                        'idx_event_issue_environment',
                        'idx_event_issue_release',
                    ],
                    MDK::INDEXES => [
                        ['columns' => ['event_id'], 'unique' => true, 'name' => 'uniq_event_id'],
                    ],
                    MDK::DROP_COLUMNS => ['project_id'],
                ],
            ],
        ]);
    }
}
