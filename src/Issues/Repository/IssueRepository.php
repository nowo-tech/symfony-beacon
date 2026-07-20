<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\IssueListSort;
use App\Project\Entity\Project;
use App\Shared\IssueStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Issue>
 */
class IssueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Issue::class);
    }

    public function findOneByProjectAndFingerprint(Project $project, string $fingerprint): ?Issue
    {
        return $this->findOneBy(['project' => $project, 'fingerprint' => $fingerprint]);
    }

    /**
     * @return list<Issue>
     */
    public function search(
        Project $project,
        ?string $query = null,
        ?string $level = null,
        ?IssueStatus $status = null,
        ?string $environment = null,
        ?User $assignee = null,
        bool $unassignedOnly = false,
        ?IssueListSort $sort = null,
    ): array {
        $sort ??= new IssueListSort(IssueListSort::DEFAULT_FIELD, IssueListSort::DEFAULT_DIRECTION);

        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->setParameter('project', $project)
            ->setMaxResults(100);

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('i.title LIKE :q OR i.culprit LIKE :q')
                ->setParameter('q', '%'.trim($query).'%');
        }
        if (null !== $level && '' !== $level) {
            $qb->andWhere('i.level = :level')->setParameter('level', $level);
        }
        if ($status instanceof IssueStatus) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }
        if ($unassignedOnly) {
            $qb->andWhere('i.assignee IS NULL');
        } elseif ($assignee instanceof User) {
            $qb->andWhere('i.assignee = :assignee')->setParameter('assignee', $assignee);
        }
        if (null !== $environment && '' !== $environment) {
            $qb->innerJoin('i.events', 'e')
                ->andWhere('e.environment = :env')
                ->setParameter('env', $environment)
                ->distinct();
        }

        if ($sort->isSqlSortable()) {
            $this->applySqlSort($qb, $sort);
        } else {
            // Occurrence windows are sorted in PHP after stats are loaded.
            $qb->orderBy('i.lastSeen', 'DESC');
        }

        /** @var list<Issue> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    private function applySqlSort(QueryBuilder $qb, IssueListSort $sort): void
    {
        $dir = strtoupper($sort->direction);

        match ($sort->field) {
            'title' => $qb->orderBy('i.title', $dir)->addOrderBy('i.id', 'DESC'),
            'level' => $qb->orderBy('i.level', $dir)->addOrderBy('i.lastSeen', 'DESC'),
            'events' => $qb->orderBy('i.eventCount', $dir)->addOrderBy('i.lastSeen', 'DESC'),
            'first_seen' => $qb->orderBy('i.firstSeen', $dir)->addOrderBy('i.id', 'DESC'),
            'last_seen' => $qb->orderBy('i.lastSeen', $dir)->addOrderBy('i.id', 'DESC'),
            'assignee' => $qb
                ->leftJoin('i.assignee', 'assignee_user')
                ->addSelect('assignee_user')
                ->orderBy('assignee_user.displayName', $dir)
                ->addOrderBy('assignee_user.email', $dir)
                ->addOrderBy('i.lastSeen', 'DESC'),
            default => $qb->orderBy('i.lastSeen', 'DESC'),
        };
    }
}
