<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\AssignmentScope;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\Form\DashboardAssignmentsFilterType;
use App\Issues\IssueListSort;
use App\Issues\Repository\IssueSearchRepository;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectFilter;
use App\Shared\Form\GetFilterFormFactory;
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
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectMembershipRepository $membershipRepository,
        private readonly IssueSearchRepository $issueRepository,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/dashboard/assignments', name: 'dashboard_assignments', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $accessible = $this->projectRepository->findAccessibleByUser($user);

        $scope = AssignmentScope::tryFromQuery($request->query->getString('scope') ?: null);
        $projectFilter = AccessibleProjectFilter::resolve($accessible, $request->query->getString('project'));
        $projects = $projectFilter instanceof Project ? [$projectFilter] : $accessible;

        $statusParam = $request->query->getString('status');
        $status = '' === $statusParam
            ? IssueStatus::Unresolved
            : (IssueStatus::tryFrom($statusParam) ?? IssueStatus::Unresolved);

        $priorityParam = $request->query->getString('priority');
        $priority = '' !== $priorityParam ? IssuePriority::tryFrom($priorityParam) : null;

        $teammates = $this->collectTeammates($accessible, $user);
        $assigneeFilter = $this->resolveAssigneeFilter(
            $teammates,
            $request->query->getString('assignee'),
        );

        $q = $request->query->getString('q') ?: null;
        $level = $request->query->getString('level') ?: null;
        $sort = IssueListSort::fromQuery(
            $request->query->getString('sort') ?: null,
            $request->query->getString('dir') ?: null,
        );

        $total = $this->issueRepository->countAssignments(
            $projects,
            $scope,
            $user,
            $q,
            $level,
            $status,
            $priority,
            $assigneeFilter,
        );
        $pagination = PagePagination::fromRequest($request, $total);
        $issues = $this->issueRepository->searchAssignments(
            $projects,
            $scope,
            $user,
            $q,
            $level,
            $status,
            $priority,
            $assigneeFilter,
            $sort,
            $pagination['per_page'],
            $pagination['offset'],
        );

        $filters = [
            'scope' => $scope->value,
            'project' => $projectFilter?->getUuid() ?? '',
            'q' => $q ?? '',
            'level' => $level ?? '',
            'status' => $status->value,
            'priority' => $priority instanceof IssuePriority ? $priority->value : '',
            'assignee' => null !== $assigneeFilter?->getId() ? (string) $assigneeFilter->getId() : '',
            'sort' => $sort->field,
            'dir' => $sort->direction,
            'page' => 1,
            'per_page' => $pagination['per_page'],
        ];

        $projectChoices = AccessibleProjectFilter::choiceMap($accessible);
        $teammateChoices = [];
        foreach ($teammates as $teammate) {
            $teammateId = $teammate->getId();
            if (null === $teammateId) {
                continue;
            }

            $teammateChoices[(string) $teammateId] = $teammate->getDisplayName() ?: $teammate->getEmail();
        }

        return $this->render('dashboard/assignments.html.twig', [
            'issues' => $issues,
            'projects' => $accessible,
            'teammates' => $teammates,
            'filters' => $filters,
            'filterForm' => $this->getFilterFormFactory->create(DashboardAssignmentsFilterType::class, $filters, [
                'action' => $this->generateUrl('dashboard_assignments'),
                'project_choices' => $projectChoices,
                'teammate_choices' => $teammateChoices,
            ])->createView(),
            'pagination' => $pagination,
            'scopes' => AssignmentScope::cases(),
        ]);
    }

    /**
     * @param list<User> $teammates
     */
    private function resolveAssigneeFilter(array $teammates, string $raw): ?User
    {
        if ('' === $raw || !ctype_digit($raw)) {
            return null;
        }
        $id = (int) $raw;
        foreach ($teammates as $teammate) {
            if ($teammate->getId() === $id) {
                return $teammate;
            }
        }

        return null;
    }

    /**
     * @param list<Project> $projects
     *
     * @return list<User>
     */
    private function collectTeammates(array $projects, User $viewer): array
    {
        /** @var array<int, User> $byId */
        $byId = [];
        foreach ($this->membershipRepository->findUsersByProjects($projects) as $member) {
            $id = $member->getId();
            if (null === $id || $id === $viewer->getId()) {
                continue;
            }
            $byId[$id] = $member;
        }

        return array_values($byId);
    }
}
