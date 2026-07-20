<?php

declare(strict_types=1);

namespace App\Shared\Retention;

use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Purges old telemetry by age and/or caps event count per project.
 *
 * Uses portable SQL (MySQL + SQLite tests). Does not remove projects, keys, or memberships.
 */
final readonly class RetentionPurger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private int $retentionDays,
        private int $maxEventsPerProject,
    ) {
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

        if ($this->retentionDays < 1 && $this->maxEventsPerProject < 1) {
            return $totals;
        }

        foreach ($this->projectRepository->findAll() as $project) {
            if (!$project instanceof Project || null === $project->getId()) {
                continue;
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

        $connection = $this->entityManager->getConnection();
        $events = 0;
        $issues = 0;
        $transactions = 0;
        $stats = 0;

        if ($this->retentionDays >= 1) {
            $cutoff = $now->modify(\sprintf('-%d days', $this->retentionDays))->format('Y-m-d H:i:s');

            $events += (int) $connection->executeStatement(
                'DELETE FROM event WHERE issue_id IN (SELECT id FROM issue WHERE project_id = ?) AND received_at < ?',
                [$projectId, $cutoff],
            );
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
                [$projectId, $now->modify(\sprintf('-%d days', $this->retentionDays))->format('Y-m-d')],
            );
        }

        if ($this->maxEventsPerProject >= 1) {
            $count = (int) $connection->fetchOne(
                'SELECT COUNT(e.id) FROM event e INNER JOIN issue i ON i.id = e.issue_id WHERE i.project_id = ?',
                [$projectId],
            );
            if ($count > $this->maxEventsPerProject) {
                $excess = $count - $this->maxEventsPerProject;
                // Delete oldest events first (portable: subquery by received_at)
                $ids = $connection->fetchFirstColumn(
                    'SELECT e.id FROM event e INNER JOIN issue i ON i.id = e.issue_id WHERE i.project_id = ? ORDER BY e.received_at ASC, e.id ASC LIMIT '.$excess,
                    [$projectId],
                );
                if ([] !== $ids) {
                    $placeholders = implode(',', array_fill(0, \count($ids), '?'));
                    $events += (int) $connection->executeStatement(
                        'DELETE FROM event WHERE id IN ('.$placeholders.')',
                        $ids,
                    );
                    $issues += (int) $connection->executeStatement(
                        'DELETE FROM issue WHERE project_id = ? AND id NOT IN (SELECT DISTINCT issue_id FROM event)',
                        [$projectId],
                    );
                }
            }
        }

        return compact('events', 'issues', 'transactions', 'stats');
    }
}
