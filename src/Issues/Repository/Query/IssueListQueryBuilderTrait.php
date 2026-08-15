<?php

declare(strict_types=1);

namespace App\Issues\Repository\Query;

use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\IssueListSort;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Project-scoped list/search query builders, sorting, and related count helpers.
 *
 * @phpstan-require-extends ServiceEntityRepository<Issue>
 */
trait IssueListQueryBuilderTrait
{
    /** @return list<Issue> */
    public function search(
        Project $project,
        ?string $query = null,
        ?string $level = null,
        ?IssueStatus $status = null,
        ?string $environment = null,
        ?string $release = null,
        ?IssuePriority $priority = null,
        ?User $assignee = null,
        bool $unassignedOnly = false,
        ?IssueListSort $sort = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $tag = null,
        ?string $url = null,
        ?string $user = null,
    ): array {
        $sort ??= new IssueListSort(IssueListSort::DEFAULT_FIELD, IssueListSort::DEFAULT_DIRECTION);

        $qb = $this->createFilteredQueryBuilder(
            $project,
            $query,
            $level,
            $status,
            $environment,
            $release,
            $priority,
            $assignee,
            $unassignedOnly,
            tag: $tag,
            url: $url,
            user: $user,
        );

        // Always hydrate assignee + duplicateOf for list/export/API (avoids N+1 per row).
        $qb->leftJoin('i.assignee', 'assignee_user')->addSelect('assignee_user');
        $qb->leftJoin('i.duplicateOf', 'duplicate_of')->addSelect('duplicate_of');

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

    public function countSearch(
        Project $project,
        ?string $query = null,
        ?string $level = null,
        ?IssueStatus $status = null,
        ?string $environment = null,
        ?string $release = null,
        ?IssuePriority $priority = null,
        ?User $assignee = null,
        bool $unassignedOnly = false,
        ?string $tag = null,
        ?string $url = null,
        ?string $user = null,
    ): int {
        $qb = $this->createFilteredQueryBuilder(
            $project,
            $query,
            $level,
            $status,
            $environment,
            $release,
            $priority,
            $assignee,
            $unassignedOnly,
            tag: $tag,
            url: $url,
            user: $user,
        );

        $qb->select('COUNT(i.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return list<Issue> */
    public function findByLastEnvironment(Project $project, string $environment, int $limit = 100): array
    {
        $normalized = Issue::normalizeEnvironment($environment);
        if (null === $normalized) {
            return [];
        }

        /** @var list<Issue> $result */
        $result = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.lastEnvironment = :env')
            ->setParameter('project', $project)
            ->setParameter('env', $normalized)
            ->orderBy('i.lastSeen', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countByProjectAndStatus(Project $project, IssueStatus $status): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.project = :project')
            ->andWhere('i.status = :status')
            ->setParameter('project', $project)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<int> $projectIds
     *
     * @return array<int, int>
     */
    public function countByStatusForProjectIds(array $projectIds, IssueStatus $status): array
    {
        if ([] === $projectIds) {
            return [];
        }

        /** @var list<array{projectId: int|string, cnt: int|string}> $rows */
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.project) AS projectId, COUNT(i.id) AS cnt')
            ->andWhere('i.project IN (:projects)')
            ->andWhere('i.status = :status')
            ->setParameter('projects', $projectIds)
            ->setParameter('status', $status)
            ->groupBy('i.project')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['projectId']] = (int) $row['cnt'];
        }

        return $map;
    }

    private function createFilteredQueryBuilder(
        Project $project,
        ?string $query,
        ?string $level,
        ?IssueStatus $status,
        ?string $environment,
        ?string $release,
        ?IssuePriority $priority,
        ?User $assignee,
        bool $unassignedOnly,
        ?string $tag = null,
        ?string $url = null,
        ?string $user = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->setParameter('project', $project);

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
        if ($unassignedOnly) {
            $qb->andWhere('i.assignee IS NULL');
        } elseif ($assignee instanceof User) {
            $qb->andWhere('i.assignee = :assignee')->setParameter('assignee', $assignee);
        }
        if (null !== $environment && '' !== $environment) {
            $normalizedEnv = Issue::normalizeEnvironment($environment);
            if (null !== $normalizedEnv) {
                $qb->andWhere('i.lastEnvironment = :env')
                    ->setParameter('env', $normalizedEnv);
            }
        }
        if (null !== $release && '' !== trim($release)) {
            $qb->andWhere('i.lastRelease = :release OR i.firstRelease = :release')
                ->setParameter('release', trim($release));
        }

        $this->applyTagFilter($qb, $project, $tag);
        $this->applyUrlFilter($qb, $project, $url);
        $this->applyUserFilter($qb, $user);

        return $qb;
    }

    protected function applySqlSort(QueryBuilder $qb, IssueListSort $sort): void
    {
        $dir = strtoupper($sort->direction);

        if ($sort->isOccurrenceSortable()) {
            $this->applyOccurrenceSqlSort($qb, $sort);

            return;
        }

        match ($sort->field) {
            'title' => $qb->orderBy('i.title', $dir)->addOrderBy('i.id', 'DESC'),
            'level' => $qb->orderBy('i.level', $dir)->addOrderBy('i.lastSeen', 'DESC'),
            'events' => $qb->orderBy('i.eventCount', $dir)->addOrderBy('i.lastSeen', 'DESC'),
            'first_seen' => $qb->orderBy('i.firstSeen', $dir)->addOrderBy('i.id', 'DESC'),
            'last_seen' => $qb->orderBy('i.lastSeen', $dir)->addOrderBy('i.id', 'DESC'),
            'assignee' => $qb
                ->orderBy('assignee_user.displayName', $dir)
                ->addOrderBy('assignee_user.email', $dir)
                ->addOrderBy('i.lastSeen', 'DESC'),
            default => $qb->orderBy('i.lastSeen', 'DESC')->addOrderBy('i.id', 'DESC'),
        };
    }

    private function applyOccurrenceSqlSort(QueryBuilder $qb, IssueListSort $sort): void
    {
        $now = new DateTimeImmutable('now');
        $since = match ($sort->field) {
            'events_24h' => $now->modify('-24 hours'),
            'events_7d' => $now->modify('-7 days'),
            'events_30d' => $now->modify('-30 days'),
            default => $now->modify('-24 hours'),
        };

        $qb->addSelect(
            '(SELECT COUNT(occ_e.id) FROM '.Event::class.' occ_e'
            .' WHERE occ_e.issue = i AND occ_e.receivedAt >= :occSince) AS HIDDEN occ_count',
        )
            ->setParameter('occSince', $since)
            ->orderBy('occ_count', strtoupper($sort->direction))
            ->addOrderBy('i.lastSeen', 'DESC')
            ->addOrderBy('i.id', 'DESC');
    }
}
