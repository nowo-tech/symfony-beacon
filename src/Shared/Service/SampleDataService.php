<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Service\AnalyticsDemoSeeder;
use App\Issues\Entity\Issue;
use App\Issues\Service\IssueSampleSeeder;
use App\Performance\Entity\PerfTransaction;
use App\Performance\Service\PerformanceDemoSeeder;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Sample telemetry profiles + purge for a target project (default slug=demo).
 */
final readonly class SampleDataService
{
    /** @var array<string, array{issues: int, events: int, analytics_days: int}> */
    public const array PROFILES = [
        'dev' => ['issues' => 40, 'events' => 200, 'analytics_days' => 14],
        'load' => ['issues' => 2000, 'events' => 10000, 'analytics_days' => 90],
        'huge' => ['issues' => 20000, 'events' => 100000, 'analytics_days' => 180],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private IssueSampleSeeder $issueSampleSeeder,
        private AnalyticsDemoSeeder $analyticsDemoSeeder,
        private PerformanceDemoSeeder $performanceDemoSeeder,
    ) {
    }

    public function resolveProject(string $slug): Project
    {
        $project = $this->projectRepository->findOneBy(['slug' => $slug]);
        if (!$project instanceof Project) {
            throw new InvalidArgumentException(\sprintf('Project slug "%s" not found. Run app:seed-demo first or pass --project=.', $slug));
        }

        return $project;
    }

    /**
     * @return array{issues: int, events: int, analytics: bool, performance: bool}
     */
    public function seed(Project $project, string $profile): array
    {
        if (!isset(self::PROFILES[$profile])) {
            throw new InvalidArgumentException(\sprintf('Unknown profile "%s". Use: %s', $profile, implode(', ', array_keys(self::PROFILES))));
        }

        $cfg = self::PROFILES[$profile];
        $counts = $this->issueSampleSeeder->seed($project, $cfg['issues'], $cfg['events']);
        $analytics = $this->analyticsDemoSeeder->seedWindow($project, $cfg['analytics_days']);
        $performance = $this->performanceDemoSeeder->seedIfEmpty($project);

        return [
            'issues' => $counts['issues'],
            'events' => $counts['events'],
            'analytics' => $analytics,
            'performance' => $performance,
        ];
    }

    /**
     * Removes issues/events/perf/stats for the project; keeps project, keys, members.
     *
     * @return array{issues: int, transactions: int, stats: int}
     */
    public function purge(Project $project): array
    {
        $issueCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Issue::class, 'i')
            ->where('i.project = :p')
            ->setParameter('p', $project)
            ->getQuery()
            ->getSingleScalarResult();

        $txCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(PerfTransaction::class, 't')
            ->where('t.project = :p')
            ->setParameter('p', $project)
            ->getQuery()
            ->getSingleScalarResult();

        $statCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(DailyProjectStat::class, 's')
            ->where('s.project = :p')
            ->setParameter('p', $project)
            ->getQuery()
            ->getSingleScalarResult();

        // Events/history/comments cascade via ORM orphanRemoval / FK when issues are removed.
        $this->entityManager->createQuery('DELETE FROM '.Issue::class.' i WHERE i.project = :p')
            ->setParameter('p', $project)
            ->execute();
        $this->entityManager->createQuery('DELETE FROM '.PerfTransaction::class.' t WHERE t.project = :p')
            ->setParameter('p', $project)
            ->execute();
        $this->entityManager->createQuery('DELETE FROM '.DailyProjectStat::class.' s WHERE s.project = :p')
            ->setParameter('p', $project)
            ->execute();
        $this->entityManager->clear();

        return [
            'issues' => $issueCount,
            'transactions' => $txCount,
            'stats' => $statCount,
        ];
    }
}
