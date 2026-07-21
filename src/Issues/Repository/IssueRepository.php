<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\IssueListSort;
use App\Project\Entity\Project;
use App\Shared\IssuePriority;
use App\Shared\IssueStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
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
            forCount: true,
            tag: $tag,
            url: $url,
            user: $user,
        );

        if (null !== $environment && '' !== $environment) {
            $qb->select('COUNT(DISTINCT i.id)');
        } else {
            $qb->select('COUNT(i.id)');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Issues whose lastEnvironment matches (for environment compare).
     *
     * @return list<Issue>
     */
    public function findByLastEnvironment(Project $project, string $environment, int $limit = 500): array
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

    public function findOneByProjectAndUuid(Project $project, string $uuid): ?Issue
    {
        return $this->findOneBy(['project' => $project, 'uuid' => $uuid]);
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
     * Other issues in the project for duplicate target selection (excludes $exclude).
     *
     * @return list<Issue>
     */
    public function findDuplicateCandidates(Project $project, Issue $exclude, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.id != :excludeId')
            ->setParameter('project', $project)
            ->setParameter('excludeId', $exclude->getId() ?? 0)
            ->orderBy('i.lastSeen', 'DESC')
            ->setMaxResults($limit);

        /** @var list<Issue> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
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
        bool $forCount = false,
        ?string $tag = null,
        ?string $url = null,
        ?string $user = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->setParameter('project', $project);

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
        if ($priority instanceof IssuePriority) {
            $qb->andWhere('i.priority = :priority')->setParameter('priority', $priority);
        }
        if ($unassignedOnly) {
            $qb->andWhere('i.assignee IS NULL');
        } elseif ($assignee instanceof User) {
            $qb->andWhere('i.assignee = :assignee')->setParameter('assignee', $assignee);
        }
        if (null !== $environment && '' !== $environment) {
            $qb->innerJoin('i.events', 'e')
                ->andWhere('e.environment = :env')
                ->setParameter('env', $environment);
            if (!$forCount) {
                $qb->distinct();
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

    private function applyTagFilter(QueryBuilder $qb, Project $project, ?string $tag): void
    {
        if (null === $tag || '' === trim($tag) || null === $project->getId()) {
            return;
        }

        $needle = trim($tag);
        $ids = $this->issueIdsMatchingPayload($project, $needle, useJsonSearch: true);
        $this->restrictToIssueIds($qb, $ids, 'tagFilterIssueIds');
    }

    private function applyUrlFilter(QueryBuilder $qb, Project $project, ?string $url): void
    {
        if (null === $url || '' === trim($url) || null === $project->getId()) {
            return;
        }

        $needle = trim($url);
        $ids = $this->issueIdsMatchingPayload($project, $needle, useJsonSearch: false);
        $this->restrictToIssueIds($qb, $ids, 'urlFilterIssueIds');
    }

    private function applyUserFilter(QueryBuilder $qb, ?string $user): void
    {
        if (null === $user || '' === trim($user)) {
            return;
        }

        $qb->andWhere(
            'EXISTS (SELECT 1 FROM '.Event::class.' ue WHERE ue.issue = i AND ue.userIdentifier LIKE :userLike)',
        )->setParameter('userLike', '%'.trim($user).'%');
    }

    /**
     * @return list<int>
     */
    private function issueIdsMatchingPayload(Project $project, string $needle, bool $useJsonSearch): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform();
        $projectId = $project->getId();
        if (null === $projectId) {
            return [];
        }

        $isSqlite = $platform instanceof SQLitePlatform;
        $isMysql = $platform instanceof AbstractMySQLPlatform;

        if ($useJsonSearch && $isMysql) {
            $sql = 'SELECT DISTINCT e.issue_id FROM event e'
                .' INNER JOIN issue i ON i.id = e.issue_id'
                .' WHERE i.project_id = ? AND JSON_SEARCH(e.payload, \'one\', ?) IS NOT NULL';
            $params = [$projectId, $needle];
        } else {
            $like = '%'.$this->escapeLike($needle).'%';
            if ($isSqlite) {
                $sql = 'SELECT DISTINCT e.issue_id FROM event e'
                    .' INNER JOIN issue i ON i.id = e.issue_id'
                    .' WHERE i.project_id = ? AND CAST(e.payload AS TEXT) LIKE ? ESCAPE \'\\\'';
            } else {
                $sql = 'SELECT DISTINCT e.issue_id FROM event e'
                    .' INNER JOIN issue i ON i.id = e.issue_id'
                    .' WHERE i.project_id = ? AND CAST(e.payload AS CHAR) LIKE ? ESCAPE \'\\\'';
            }
            $params = [$projectId, $like];
        }

        /** @var list<int|string> $rows */
        $rows = $conn->fetchFirstColumn($sql, $params);

        return array_map(static fn (int|string $id): int => (int) $id, $rows);
    }

    /**
     * @param list<int> $ids
     */
    private function restrictToIssueIds(QueryBuilder $qb, array $ids, string $param): void
    {
        if ([] === $ids) {
            $qb->andWhere('1 = 0');

            return;
        }

        $qb->andWhere('i.id IN (:'.$param.')')->setParameter($param, $ids);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function applySqlSort(QueryBuilder $qb, IssueListSort $sort): void
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
                ->leftJoin('i.assignee', 'assignee_user')
                ->addSelect('assignee_user')
                ->orderBy('assignee_user.displayName', $dir)
                ->addOrderBy('assignee_user.email', $dir)
                ->addOrderBy('i.lastSeen', 'DESC'),
            default => $qb->orderBy('i.lastSeen', 'DESC')->addOrderBy('i.id', 'DESC'),
        };
    }

    /**
     * Order by event counts in a sliding window via a correlated SQL subquery (not PHP).
     */
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
