<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\AssignmentScope;
use App\Issues\Dto\DashboardAssignmentsFilters;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\IssueListSort;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Service\DashboardProjectSelectionResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves dashboard Assignments filters from the request and actor access.
 */
final readonly class DashboardAssignmentsFilterResolver
{
    public function __construct(
        private DashboardProjectSelectionResolver $projectSelection,
        private ProjectMembershipRepository $membershipRepository,
    ) {
    }

    public function resolve(User $user, Request $request): DashboardAssignmentsFilters
    {
        $selection = $this->projectSelection->resolve($user, $request);
        $accessible = $selection['accessible'];
        $scope = AssignmentScope::tryFromQuery($request->query->getString('scope') ?: null);
        $project = $selection['project'];
        $projects = $selection['selected'];

        $statusParam = $request->query->getString('status');
        $status = '' === $statusParam
            ? IssueStatus::Unresolved
            : (IssueStatus::tryFrom($statusParam) ?? IssueStatus::Unresolved);

        $priorityParam = $request->query->getString('priority');
        $priority = '' !== $priorityParam ? IssuePriority::tryFrom($priorityParam) : null;

        $teammates = $this->collectTeammates($accessible, $user);

        return new DashboardAssignmentsFilters(
            accessibleProjects: $accessible,
            selectedProjects: $projects,
            teammates: $teammates,
            scope: $scope,
            project: $project,
            query: $request->query->getString('q') ?: null,
            level: $request->query->getString('level') ?: null,
            status: $status,
            priority: $priority,
            assignee: $this->resolveAssigneeFilter($teammates, $request->query->getString('assignee')),
            sort: IssueListSort::fromQuery(
                $request->query->getString('sort') ?: null,
                $request->query->getString('dir') ?: null,
            ),
        );
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
