<?php

declare(strict_types=1);

namespace App\Issues\Repository\Query;

use App\Identity\Entity\User;
use App\Issues\AssignmentScope;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\IssueListSort;
use App\Project\Entity\Project;
use Doctrine\ORM\QueryBuilder;

/**
 * Cross-project assignment list/count query builders.
 */
trait IssueAssignmentQueryTrait
{
    /**
     * @param list<Project> $projects
     *
     * @return list<Issue>
     */
    public function searchAssignments(
        array $projects,
        AssignmentScope $scope,
        User $viewer,
        ?string $query = null,
        ?string $level = null,
        ?IssueStatus $status = null,
        ?IssuePriority $priority = null,
        ?User $assigneeFilter = null,
        ?IssueListSort $sort = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        if ([] === $projects) {
            return [];
        }

        $sort ??= new IssueListSort(IssueListSort::DEFAULT_FIELD, IssueListSort::DEFAULT_DIRECTION);
        $qb = $this->createAssignmentQueryBuilder(
            $projects,
            $scope,
            $viewer,
            $query,
            $level,
            $status,
            $priority,
            $assigneeFilter,
        );
        $qb->leftJoin('i.assignee', 'assignee_user')->addSelect('assignee_user');
        $qb->leftJoin('i.project', 'assignment_project')->addSelect('assignment_project');
        $this->applySqlSort($qb, $sort);

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }
        if (null !== $offset && $offset > 0) {
            $qb->setFirstResult($offset);
        }

        /** @var list<Issue> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /** @param list<Project> $projects */
    public function countAssignments(
        array $projects,
        AssignmentScope $scope,
        User $viewer,
        ?string $query = null,
        ?string $level = null,
        ?IssueStatus $status = null,
        ?IssuePriority $priority = null,
        ?User $assigneeFilter = null,
    ): int {
        if ([] === $projects) {
            return 0;
        }

        $qb = $this->createAssignmentQueryBuilder(
            $projects,
            $scope,
            $viewer,
            $query,
            $level,
            $status,
            $priority,
            $assigneeFilter,
        );
        $qb->select('COUNT(i.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @param list<Project> $projects */
    private function createAssignmentQueryBuilder(
        array $projects,
        AssignmentScope $scope,
        User $viewer,
        ?string $query,
        ?string $level,
        ?IssueStatus $status,
        ?IssuePriority $priority,
        ?User $assigneeFilter,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project IN (:projects)')
            ->setParameter('projects', $projects);

        if (null !== $query && '' !== trim($query)) {
            $this->applyFullTextOrLikeQuery($qb, trim($query));
        }
        if (null !== $level && '' !== $level) {
            $qb->andWhere('i.level = :level')->setParameter('level', $level);
        }
        if ($status instanceof IssueStatus) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }
        if ($priority instanceof IssuePriority) {
            $qb->andWhere('i.priority = :priority')->setParameter('priority', $priority);
        }

        match ($scope) {
            AssignmentScope::Mine => $qb
                ->andWhere('i.assignee = :viewer')
                ->setParameter('viewer', $viewer),
            AssignmentScope::Unassigned => $qb->andWhere('i.assignee IS NULL'),
            AssignmentScope::Teammates => $this->applyTeammatesAssigneeFilter($qb, $viewer, $assigneeFilter),
            AssignmentScope::All => $this->applyOptionalAssigneeFilter($qb, $assigneeFilter),
        };

        return $qb;
    }

    private function applyTeammatesAssigneeFilter(QueryBuilder $qb, User $viewer, ?User $assigneeFilter): void
    {
        if ($assigneeFilter instanceof User) {
            $qb->andWhere('i.assignee = :assigneeFilter')
                ->andWhere('i.assignee != :viewer')
                ->setParameter('assigneeFilter', $assigneeFilter)
                ->setParameter('viewer', $viewer);

            return;
        }

        $qb->andWhere('i.assignee IS NOT NULL')
            ->andWhere('i.assignee != :viewer')
            ->setParameter('viewer', $viewer);
    }

    private function applyOptionalAssigneeFilter(QueryBuilder $qb, ?User $assigneeFilter): void
    {
        if ($assigneeFilter instanceof User) {
            $qb->andWhere('i.assignee = :assigneeFilter')
                ->setParameter('assigneeFilter', $assigneeFilter);
        }
    }
}
