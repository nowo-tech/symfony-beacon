<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Issues\Dto\IssueIndexFilters;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\IssueListSort;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves per-project issue index filters from the query string.
 */
final readonly class IssueIndexFilterResolver
{
    public function __construct(
        private ProjectMembershipRepository $membershipRepository,
    ) {
    }

    public function resolve(Project $project, Request $request): IssueIndexFilters
    {
        $statusParam = $request->query->getString('status');
        $status = '' !== $statusParam
            ? (IssueStatus::tryFrom($statusParam) ?? IssueStatus::Unresolved)
            : IssueStatus::Unresolved;

        $members = $this->membershipRepository->findUsersByProject($project);
        $assigneeFilter = $request->query->getString('assignee');
        $assignee = null;
        $unassignedOnly = 'unassigned' === $assigneeFilter;
        if (!$unassignedOnly && '' !== $assigneeFilter && ctype_digit($assigneeFilter)) {
            foreach ($members as $member) {
                if ($member->getId() === (int) $assigneeFilter) {
                    $assignee = $member;
                    break;
                }
            }
        }

        $priorityParam = $request->query->getString('priority');

        return new IssueIndexFilters(
            members: $members,
            query: $request->query->getString('q') ?: null,
            level: $request->query->getString('level') ?: null,
            status: $status,
            priority: '' !== $priorityParam ? IssuePriority::tryFrom($priorityParam) : null,
            environment: $request->query->getString('environment') ?: null,
            release: $request->query->getString('release') ?: null,
            compare: $request->query->getString('compare') ?: null,
            tag: $request->query->getString('tag') ?: null,
            url: $request->query->getString('url') ?: null,
            user: $request->query->getString('user') ?: null,
            assignee: $assignee,
            unassignedOnly: $unassignedOnly,
            assigneeFilter: $assigneeFilter,
            sort: IssueListSort::fromQuery(
                $request->query->getString('sort') ?: null,
                $request->query->getString('dir') ?: null,
            ),
        );
    }
}
