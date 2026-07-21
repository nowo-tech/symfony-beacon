<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Removes project telemetry while keeping the project, members, and API keys.
 *
 * Uses portable SQL (compatible with MySQL and SQLite test databases).
 */
final readonly class ProjectHistoryClearer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function clear(Project $project): void
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return;
        }

        $connection = $this->entityManager->getConnection();

        $connection->executeStatement(
            'DELETE FROM issue_history WHERE issue_id IN (SELECT id FROM issue WHERE project_id = ?)',
            [$projectId],
        );
        $connection->executeStatement(
            'DELETE FROM event WHERE issue_id IN (SELECT id FROM issue WHERE project_id = ?)',
            [$projectId],
        );
        $connection->executeStatement('DELETE FROM issue WHERE project_id = ?', [$projectId]);

        $connection->executeStatement(
            'DELETE FROM perf_span WHERE transaction_id IN (SELECT id FROM perf_transaction WHERE project_id = ?)',
            [$projectId],
        );
        $connection->executeStatement('DELETE FROM perf_transaction WHERE project_id = ?', [$projectId]);

        $connection->executeStatement('DELETE FROM daily_project_stat WHERE project_id = ?', [$projectId]);

        $this->entityManager->clear();
    }
}
