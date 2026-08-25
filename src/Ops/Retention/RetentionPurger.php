<?php

declare(strict_types=1);

namespace App\Ops\Retention;

use App\Issues\Service\IssueMergeService;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Purges old telemetry by age and/or caps event count per project.
 *
 * Prefers per-project overrides, then instance operational defaults.
 * Uses portable SQL (MySQL + SQLite tests). Does not remove projects, keys, or memberships.
 * After deleting events, recomputes issue denormalized aggregates so counters stay truthful.
 *
 * Event deletes run in batches ({@see self::DEFAULT_DELETE_BATCH_SIZE}) to avoid long locks
 * and huge ID lists when a project holds millions of rows.
 */
final readonly class RetentionPurger
{
    public const int DEFAULT_DELETE_BATCH_SIZE = 1000;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private IssueMergeService $issueMergeService,
        private ProjectGovernanceResolver $governanceResolver,
        private int $deleteBatchSize = self::DEFAULT_DELETE_BATCH_SIZE,
    ) {
        // Readonly promoted properties cannot be reassigned; clamp via max() at use sites.
    }

    /**
     * @return array{projects: int, events: int, issues: int, transactions: int, stats: int}
     */
    public function purge(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $totals = [
            'projects' => 0,
            'events' => 0,
            'issues' => 0,
            'transactions' => 0,
            'stats' => 0,
        ];

        $eligible = [];
        foreach ($this->projectRepository->findAll() as $project) {
            if (!$project instanceof Project || null === $project->getId()) {
                continue;
            }
            $days = $this->governanceResolver->effectiveRetentionDays($project);
            $maxEvents = $this->governanceResolver->effectiveRetentionMaxEvents($project);
            if ($days < 1 && $maxEvents < 1) {
                continue;
            }
            $eligible[] = $project;
        }

        foreach ($eligible as $project) {
            // purgeProject may clear the EM after event deletes — reload detached entities.
            if (!$this->entityManager->contains($project)) {
                $projectId = $project->getId();
                if (null === $projectId) {
                    continue;
                }
                $reloaded = $this->projectRepository->find($projectId);
                if (!$reloaded instanceof Project) {
                    continue;
                }
                $project = $reloaded;
            }

            ++$totals['projects'];
            $result = $this->purgeProject($project, $now);
            $totals['events'] += $result['events'];
            $totals['issues'] += $result['issues'];
            $totals['transactions'] += $result['transactions'];
            $totals['stats'] += $result['stats'];
        }

        $this->entityManager->clear();

        return $totals;
    }

    /**
     * @return array{events: int, issues: int, transactions: int, stats: int}
     */
    public function purgeProject(Project $project, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $projectId = $project->getId();
        if (null === $projectId) {
            return ['events' => 0, 'issues' => 0, 'transactions' => 0, 'stats' => 0];
        }

        $retentionDays = $this->governanceResolver->effectiveRetentionDays($project);
        $maxEvents = $this->governanceResolver->effectiveRetentionMaxEvents($project);

        $connection = $this->entityManager->getConnection();
        $batchSize = max(1, $this->deleteBatchSize);
        $events = 0;
        $issues = 0;
        $transactions = 0;
        $stats = 0;
        $deletedEvents = false;

        if ($retentionDays >= 1) {
            $cutoff = $now->modify(\sprintf('-%d days', $retentionDays))->format('Y-m-d H:i:s');

            $deleted = $this->deleteEventsInBatches(
                $connection,
                'SELECT e.id FROM event e WHERE e.project_id = ? AND e.received_at < ? ORDER BY e.received_at ASC, e.id ASC',
                [$projectId, $cutoff],
                $batchSize,
            );
            $events += $deleted;
            $deletedEvents = $deleted > 0;
            $issues += (int) $connection->executeStatement(
                'DELETE FROM issue WHERE project_id = ? AND id NOT IN (SELECT DISTINCT issue_id FROM event)',
                [$projectId],
            );

            $connection->executeStatement(
                'DELETE FROM perf_span WHERE transaction_id IN (SELECT id FROM perf_transaction WHERE project_id = ? AND received_at < ?)',
                [$projectId, $cutoff],
            );
            $transactions += (int) $connection->executeStatement(
                'DELETE FROM perf_transaction WHERE project_id = ? AND received_at < ?',
                [$projectId, $cutoff],
            );
            $stats += (int) $connection->executeStatement(
                'DELETE FROM daily_project_stat WHERE project_id = ? AND stat_date < ?',
                [$projectId, $now->modify(\sprintf('-%d days', $retentionDays))->format('Y-m-d')],
            );
        }

        if ($maxEvents >= 1) {
            $count = (int) $connection->fetchOne(
                'SELECT COUNT(e.id) FROM event e WHERE e.project_id = ?',
                [$projectId],
            );
            if ($count > $maxEvents) {
                $remaining = $count - $maxEvents;
                while ($remaining > 0) {
                    $limit = min($batchSize, $remaining);
                    $ids = $connection->fetchFirstColumn(
                        'SELECT e.id FROM event e WHERE e.project_id = ? ORDER BY e.received_at ASC, e.id ASC LIMIT '.$limit,
                        [$projectId],
                    );
                    if ([] === $ids) {
                        break;
                    }
                    $placeholders = implode(',', array_fill(0, \count($ids), '?'));
                    $deleted = (int) $connection->executeStatement(
                        'DELETE FROM event WHERE id IN ('.$placeholders.')',
                        $ids,
                    );
                    $events += $deleted;
                    $deletedEvents = $deletedEvents || $deleted > 0;
                    $remaining -= \count($ids);
                }
                $issues += (int) $connection->executeStatement(
                    'DELETE FROM issue WHERE project_id = ? AND id NOT IN (SELECT DISTINCT issue_id FROM event)',
                    [$projectId],
                );
            }
        }

        if ($deletedEvents) {
            // Raw DELETE bypasses the unit of work; refresh before recomputing aggregates.
            $this->entityManager->clear();
            $reloaded = $this->projectRepository->find($projectId);
            if ($reloaded instanceof Project) {
                $this->issueMergeService->recomputeAggregatesForProject($reloaded);
            }
        }

        return ['events' => $events, 'issues' => $issues, 'transactions' => $transactions, 'stats' => $stats];
    }

    /**
     * @param list<mixed> $params
     */
    private function deleteEventsInBatches(Connection $connection, string $selectSql, array $params, int $batchSize): int
    {
        $total = 0;
        do {
            $ids = $connection->fetchFirstColumn($selectSql.' LIMIT '.$batchSize, $params);
            if ([] === $ids) {
                break;
            }
            $placeholders = implode(',', array_fill(0, \count($ids), '?'));
            $deleted = (int) $connection->executeStatement(
                'DELETE FROM event WHERE id IN ('.$placeholders.')',
                $ids,
            );
            $total += $deleted;
        } while (\count($ids) >= $batchSize);

        return $total;
    }
}
