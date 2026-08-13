<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Dto\DashboardNewInReleaseFilters;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueSearchRepository;
use App\Project\Entity\Project;
use App\Project\Service\AccessibleProjectFilter;
use App\Project\Service\AccessibleProjectsProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves dashboard New-in-release filters from the request and actor access.
 */
final readonly class DashboardNewInReleaseFilterResolver
{
    public function __construct(
        private AccessibleProjectsProvider $accessibleProjects,
        private IssueSearchRepository $issueRepository,
    ) {
    }

    public function resolve(User $user, Request $request): DashboardNewInReleaseFilters
    {
        $accessible = $this->accessibleProjects->forUser($user);
        $project = AccessibleProjectFilter::resolve($accessible, $request->query->getString('project'));
        $projects = $project instanceof Project ? [$project] : $accessible;

        $releaseRaw = trim($request->query->getString('release'));
        $release = '' !== $releaseRaw ? Issue::normalizeRelease($releaseRaw) : null;

        return new DashboardNewInReleaseFilters(
            accessibleProjects: $accessible,
            selectedProjects: $projects,
            availableReleases: $this->issueRepository->findDistinctFirstReleasesAcrossProjects($projects),
            project: $project,
            release: $release,
        );
    }
}
