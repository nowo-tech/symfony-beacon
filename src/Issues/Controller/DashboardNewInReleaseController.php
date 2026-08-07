<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueSearchRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectFilter;
use App\Shared\Pagination\PagePagination;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cross-project “new in release” issues in the Dashboard section.
 */
#[IsGranted('ROLE_USER')]
final class DashboardNewInReleaseController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly IssueSearchRepository $issueRepository,
    ) {
    }

    #[Route('/dashboard/new-in-release', name: 'dashboard_new_in_release', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $accessible = $this->projectRepository->findAccessibleByUser($user);
        $projectFilter = AccessibleProjectFilter::resolve($accessible, $request->query->getString('project'));
        $projects = null !== $projectFilter ? [$projectFilter] : $accessible;

        $releaseRaw = trim($request->query->getString('release'));
        $release = '' !== $releaseRaw ? Issue::normalizeRelease($releaseRaw) : null;

        $total = $this->issueRepository->countNewInRelease($projects, $release);
        $pagination = PagePagination::fromRequest($request, $total);
        $issues = $this->issueRepository->searchNewInRelease(
            $projects,
            $release,
            $pagination['per_page'],
            $pagination['offset'],
        );

        return $this->render('dashboard/new_in_release.html.twig', [
            'issues' => $issues,
            'projects' => $accessible,
            'releases' => $this->issueRepository->findDistinctFirstReleasesAcrossProjects($projects),
            'filters' => [
                'project' => $projectFilter?->getUuid() ?? '',
                'release' => $release ?? '',
                'per_page' => $pagination['per_page'],
            ],
            'pagination' => $pagination,
        ]);
    }
}
