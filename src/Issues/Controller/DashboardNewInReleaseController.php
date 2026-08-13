<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Form\DashboardNewInReleaseFilterType;
use App\Issues\Repository\IssueSearchRepository;
use App\Issues\Service\DashboardNewInReleaseFilterResolver;
use App\Shared\Form\GetFilterFormFactory;
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
        private readonly IssueSearchRepository $issueRepository,
        private readonly DashboardNewInReleaseFilterResolver $filterResolver,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/dashboard/new-in-release', name: 'dashboard_new_in_release', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $filters = $this->filterResolver->resolve($user, $request);

        $total = $this->issueRepository->countNewInRelease($filters->selectedProjects, $filters->release);
        $pagination = PagePagination::fromRequest($request, $total);
        $issues = $this->issueRepository->searchNewInRelease(
            $filters->selectedProjects,
            $filters->release,
            $pagination['per_page'],
            $pagination['offset'],
        );
        $formData = $filters->formData($pagination['per_page']);

        return $this->render('dashboard/new_in_release.html.twig', [
            'issues' => $issues,
            'projects' => $filters->accessibleProjects,
            'releases' => $filters->availableReleases,
            'filters' => $formData,
            'filterForm' => $this->getFilterFormFactory->create(DashboardNewInReleaseFilterType::class, $formData, [
                'action' => $this->generateUrl('dashboard_new_in_release'),
                'project_choices' => $filters->projectChoices(),
                'release_choices' => $filters->releaseChoices(),
            ])->createView(),
            'pagination' => $pagination,
        ]);
    }
}
