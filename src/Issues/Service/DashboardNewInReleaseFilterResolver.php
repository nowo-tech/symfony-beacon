<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Dto\DashboardNewInReleaseFilters;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueSearchRepository;
use App\Project\Service\DashboardProjectSelectionResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves dashboard New-in-release filters from the request and actor access.
 */
final readonly class DashboardNewInReleaseFilterResolver
{
    public function __construct(
        private DashboardProjectSelectionResolver $projectSelection,
        private IssueSearchRepository $issueRepository,
    ) {
    }

    public function resolve(User $user, Request $request): DashboardNewInReleaseFilters
    {
        $selection = $this->projectSelection->resolve($user, $request);

        $releaseRaw = trim($request->query->getString('release'));
        $release = '' !== $releaseRaw ? Issue::normalizeRelease($releaseRaw) : null;

        return new DashboardNewInReleaseFilters(
            accessibleProjects: $selection['accessible'],
            selectedProjects: $selection['selected'],
            availableReleases: $this->issueRepository->findDistinctFirstReleasesAcrossProjects($selection['selected']),
            project: $selection['project'],
            release: $release,
        );
    }
}
