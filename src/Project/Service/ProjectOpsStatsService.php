<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use App\Shared\IssueStatus;
use DateTimeImmutable;

/**
 * Aggregate ops metrics for admin project list/show.
 *
 * @phpstan-type ProjectOpsStats array{
 *     open_issues: int,
 *     events_last_7d: int,
 *     last_ingest_at: ?DateTimeImmutable
 * }
 */
final readonly class ProjectOpsStatsService
{
    public function __construct(
        private IssueRepository $issueRepository,
        private EventRepository $eventRepository,
    ) {
    }

    /**
     * @return ProjectOpsStats
     */
    public function forProject(Project $project): array
    {
        return [
            'open_issues' => $this->issueRepository->countByProjectAndStatus($project, IssueStatus::Unresolved),
            'events_last_7d' => $this->eventRepository->countReceivedSinceForProject(
                $project,
                new DateTimeImmutable('-7 days'),
            ),
            'last_ingest_at' => $this->eventRepository->findLastReceivedAtForProject($project),
        ];
    }

    /**
     * @param list<Project> $projects
     *
     * @return array<int, ProjectOpsStats> keyed by project id
     */
    public function forProjects(array $projects): array
    {
        $map = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            if (null === $id) {
                continue;
            }
            $map[$id] = $this->forProject($project);
        }

        return $map;
    }
}
