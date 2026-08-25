<?php

declare(strict_types=1);

namespace App\Ops\Service;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Ops\Messenger\MessengerQueueHealth;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectOpsStatsService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Read-only fleet ops aggregates for the admin Ops overview (`035`).
 *
 * @phpstan-type SpikeRow array{
 *     project: Project,
 *     errors_last_1d: int,
 *     avg_errors_last_7d: float,
 *     threshold: float
 * }
 * @phpstan-type OpenIssueRow array{project: Project, open_issues: int}
 * @phpstan-type FailedDeliveryRow array{destination: NotificationDestination, project: Project, label: string, type: string, last_delivery_at: ?DateTimeImmutable, error_snippet: ?string}
 * @phpstan-type OpsOverview array{
 *     messenger: array{pending: ?int, available: bool},
 *     open_issues_total: int,
 *     open_issues_by_project: list<OpenIssueRow>,
 *     suspended_count: int,
 *     suspended_projects: list<Project>,
 *     spikes: list<SpikeRow>,
 *     failed_deliveries: list<FailedDeliveryRow>,
 *     filter_project: ?Project,
 *     projects: list<Project>
 * }
 */
final readonly class OpsOverviewService
{
    public const int SPIKE_CAP = 25;
    public const int FAILED_DELIVERY_CAP = 25;
    public const int OPEN_ISSUE_PROJECT_CAP = 50;

    public function __construct(
        private MessengerQueueHealth $messengerQueueHealth,
        private ProjectRepository $projectRepository,
        private ProjectOpsStatsService $opsStats,
        private DailyProjectStatRepository $dailyProjectStatRepository,
        private NotificationDestinationRepository $destinationRepository,
    ) {
    }

    /**
     * Spike when last-day errors exceed max(3, 2 × average daily errors over 7 days).
     */
    public static function isSpike(int $errorsLast1d, float $avgErrorsLast7d): bool
    {
        return $errorsLast1d > max(3.0, 2.0 * $avgErrorsLast7d);
    }

    /**
     * @return OpsOverview
     */
    public function build(?Project $filterProject = null): array
    {
        $allProjects = $this->projectRepository->findAllOrdered();
        $scoped = $filterProject instanceof Project ? [$filterProject] : $allProjects;

        $opsById = $this->opsStats->forProjects($scoped);
        $openTotal = 0;
        $openRows = [];
        foreach ($scoped as $project) {
            $id = $project->getId();
            if (null === $id) {
                continue;
            }
            $open = $opsById[$id]['open_issues'] ?? 0;
            $openTotal += $open;
            if ($open > 0) {
                $openRows[] = ['project' => $project, 'open_issues' => $open];
            }
        }
        usort($openRows, static fn (array $a, array $b): int => $b['open_issues'] <=> $a['open_issues']);
        $openRows = \array_slice($openRows, 0, self::OPEN_ISSUE_PROJECT_CAP);

        $suspended = [];
        foreach ($scoped as $project) {
            if (!$project->isIngestEnabled()) {
                $suspended[] = $project;
            }
        }

        $statsByProject = $this->dailyProjectStatRepository->findLastDaysForProjects($scoped, 7);
        $spikes = [];
        $today = new DateTimeImmutable('now', new DateTimeZone('UTC'))->setTime(0, 0);
        foreach ($scoped as $project) {
            $id = $project->getId();
            if (null === $id) {
                continue;
            }
            /** @var list<DailyProjectStat> $days */
            $days = $statsByProject[$id] ?? [];
            $errorsLast1d = 0;
            $sum7 = 0;
            foreach ($days as $stat) {
                $count = $stat->getErrorCount();
                $sum7 += $count;
                if ($stat->getStatDate()->format('Y-m-d') === $today->format('Y-m-d')) {
                    $errorsLast1d = $count;
                }
            }
            $avg7 = $sum7 / 7.0;
            if (!self::isSpike($errorsLast1d, $avg7)) {
                continue;
            }
            $spikes[] = [
                'project' => $project,
                'errors_last_1d' => $errorsLast1d,
                'avg_errors_last_7d' => round($avg7, 2),
                'threshold' => max(3.0, 2.0 * $avg7),
            ];
        }
        usort($spikes, static fn (array $a, array $b): int => $b['errors_last_1d'] <=> $a['errors_last_1d']);
        $spikes = \array_slice($spikes, 0, self::SPIKE_CAP);

        $failedDestinations = $this->destinationRepository->findWithFailedLastDelivery(
            $filterProject,
            self::FAILED_DELIVERY_CAP,
        );
        $failedRows = [];
        foreach ($failedDestinations as $destination) {
            $project = $destination->getProject();
            if (!$project instanceof Project) {
                continue;
            }
            $type = $destination->getType();
            $failedRows[] = [
                'destination' => $destination,
                'project' => $project,
                'label' => $destination->getLabel(),
                'type' => $type->value,
                'last_delivery_at' => $destination->getLastDeliveryAt(),
                'error_snippet' => $destination->getLastDeliveryError(),
            ];
        }

        return [
            'messenger' => $this->messengerQueueHealth->asyncPending(),
            'open_issues_total' => $openTotal,
            'open_issues_by_project' => $openRows,
            'suspended_count' => \count($suspended),
            'suspended_projects' => $suspended,
            'spikes' => $spikes,
            'failed_deliveries' => $failedRows,
            'filter_project' => $filterProject,
            'projects' => $allProjects,
        ];
    }
}
