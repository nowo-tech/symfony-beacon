<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\AssignmentScope;
use App\Issues\Form\DashboardAssignmentsFilterType;
use App\Issues\Repository\IssueSearchRepository;
use App\Issues\Service\DashboardAssignmentsFilterResolver;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
use App\Shared\Pagination\PagePagination;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cross-project assignment inbox in the Dashboard section.
 */
#[IsGranted('ROLE_USER')]
final class DashboardAssignmentsController extends AbstractController
{
    public function __construct(
        private readonly IssueSearchRepository $issueRepository,
        private readonly DashboardAssignmentsFilterResolver $filterResolver,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/dashboard/assignments', name: 'dashboard_assignments', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $filters = $this->filterResolver->resolve($user, $request);

        $total = $this->issueRepository->countAssignments(
            $filters->selectedProjects,
            $filters->scope,
            $user,
            $filters->query,
            $filters->level,
            $filters->status,
            $filters->priority,
            $filters->assignee,
        );
        $pagination = PagePagination::fromRequest($request, $total);
        $issues = $this->issueRepository->searchAssignments(
            $filters->selectedProjects,
            $filters->scope,
            $user,
            $filters->query,
            $filters->level,
            $filters->status,
            $filters->priority,
            $filters->assignee,
            $filters->sort,
            $pagination['per_page'],
            $pagination['offset'],
        );
        $formData = $filters->formData($pagination['per_page']);

        return $this->render('dashboard/assignments.html.twig', [
            'issues' => $issues,
            'projects' => $filters->accessibleProjects,
            'teammates' => $filters->teammates,
            'filters' => $formData,
            'filterForm' => $this->getFilterFormFactory->create(DashboardAssignmentsFilterType::class, $formData, [
                'action' => $this->generateUrl('dashboard_assignments'),
                'project_choices' => $filters->projectChoices(),
                'teammate_choices' => $filters->teammateChoices(),
            ])->createView(),
            'pagination' => $pagination,
            'scopes' => AssignmentScope::cases(),
        ]);
    }
}
