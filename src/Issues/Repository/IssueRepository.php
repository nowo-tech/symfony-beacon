<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\AssignmentScope;
use App\Issues\IssueListSort;
use App\Project\Entity\Project;
use App\Shared\IssuePriority;
use App\Shared\IssueStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
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

    /**
     * Cross-project assignment inbox for the dashboard Assignments panel.
     *
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

    /**
     * @param list<Project> $projects
     */
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

    /**
     * @param list<Project> $projects
     */
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

    /**
     * Cross-project issues first seen in a release (dashboard New in release panel).
     *
     * @param list<Project> $projects
     *
     * @return list<Issue>
     */
    public function searchNewInRelease(
        array $projects,
        ?string $release = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        if ([] === $projects) {
            return [];
        }

        $qb = $this->createNewInReleaseQueryBuilder($projects, $release);
        $qb->leftJoin('i.project', 'nir_project')->addSelect('nir_project');
        $qb->orderBy('i.lastSeen', 'DESC')->addOrderBy('i.id', 'DESC');

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

    /**
     * @param list<Project> $projects
     */
    public function countNewInRelease(array $projects, ?string $release = null): int
    {
        if ([] === $projects) {
            return 0;
        }

        $qb = $this->createNewInReleaseQueryBuilder($projects, $release);
        $qb->select('COUNT(i.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Distinct {@see Issue::$firstRelease} values across projects, newest last-seen first.
     *
     * @param list<Project> $projects
     *
     * @return list<string>
     */
    public function findDistinctFirstReleasesAcrossProjects(array $projects, int $limit = 40): array
    {
        if ([] === $projects) {
            return [];
        }

        $ids = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }
        if ([] === $ids) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT first_release AS release_value
            FROM issue
            WHERE project_id IN (:projectIds) AND first_release IS NOT NULL AND first_release <> ''
            GROUP BY first_release
            ORDER BY MAX(last_seen) DESC, first_release DESC
            LIMIT :limit
            SQL;

        /** @var list<int|string> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'projectIds' => $ids,
            'limit' => $limit,
        ], [
            'projectIds' => ArrayParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ]);

        $releases = [];
        foreach ($rows as $row) {
            $normalized = Issue::normalizeRelease((string) $row);
            if (null === $normalized) {
                continue;
            }
            $releases[] = $normalized;
        }

        return array_values(array_unique($releases));
    }

    /**
     * @param list<Project> $projects
     */
    private function createNewInReleaseQueryBuilder(array $projects, ?string $release): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project IN (:projects)')
            ->andWhere('i.firstRelease IS NOT NULL')
            ->setParameter('projects', $projects);

        $normalized = null !== $release && '' !== $release ? Issue::normalizeRelease($release) : null;
        if (null !== $normalized) {
            $qb->andWhere('i.firstRelease = :release')->setParameter('release', $normalized);
        }

        return $qb;
    }

    /**
     * Distinct releases observed on issues (`firstRelease` or `lastRelease`), newest first.
     *
     * @return list<string>
     */
    public function findDistinctReleases(Project $project): array
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT release_value
            FROM (
                SELECT first_release AS release_value, last_seen AS seen_at
                FROM issue
                WHERE project_id = :projectId AND first_release IS NOT NULL
                UNION ALL
                SELECT last_release AS release_value, last_seen AS seen_at
                FROM issue
                WHERE project_id = :projectId AND last_release IS NOT NULL
            ) releases
            WHERE release_value <> ''
            GROUP BY release_value
            ORDER BY MAX(seen_at) DESC, release_value DESC
            SQL;

        /** @var list<int|string> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'projectId' => $projectId,
        ], [
            'projectId' => ParameterType::INTEGER,
        ]);

        $releases = [];
        foreach ($rows as $row) {
            $normalized = Issue::normalizeRelease((string) $row);
            if (null === $normalized) {
                continue;
            }
            $releases[] = $normalized;
        }

        return array_values(array_unique($releases));
    }

    /**
     * Count issues whose `firstRelease` matches the given release.
     */
    public function countNewIssuesByFirstRelease(Project $project, string $release): int
    {
        $normalized = Issue::normalizeRelease($release);
        if (null === $normalized) {
            return 0;
        }

        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.project = :project')
            ->andWhere('i.firstRelease = :release')
            ->setParameter('project', $project)
            ->setParameter('release', $normalized)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count new issues grouped by `firstRelease`.
     *
     * @return array<string, int>
     */
    public function countNewIssuesByFirstReleaseMap(Project $project): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.firstRelease AS release_value, COUNT(i.id) AS issue_count')
            ->andWhere('i.project = :project')
            ->andWhere('i.firstRelease IS NOT NULL')
            ->setParameter('project', $project)
            ->groupBy('i.firstRelease')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $normalized = Issue::normalizeRelease((string) ($row['release_value'] ?? ''));
            if (null === $normalized) {
                continue;
            }
            $counts[$normalized] = (int) ($row['issue_count'] ?? 0);
        }

        return $counts;
    }

    /**
     * Issues that belong to a release via `firstRelease` or `lastRelease`.
     *
     * @return list<Issue>
     */
    public function findByRelease(Project $project, string $release, int $limit = 500): array
    {
        $normalized = Issue::normalizeRelease($release);
        if (null === $normalized) {
            return [];
        }

        /** @var list<Issue> $result */
        $result = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.firstRelease = :release OR i.lastRelease = :release')
            ->setParameter('project', $project)
            ->setParameter('release', $normalized)
            ->orderBy('i.lastSeen', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Latest issues first seen in a release, for release-health previews.
     *
     * @return list<Issue>
     */
    public function findLatestNewIssuesByFirstRelease(Project $project, string $release, int $limit = 8): array
    {
        $normalized = Issue::normalizeRelease($release);
        if (null === $normalized) {
            return [];
        }

        /** @var list<Issue> $result */
        $result = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.firstRelease = :release')
            ->setParameter('project', $project)
            ->setParameter('release', $normalized)
            ->orderBy('i.lastSeen', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findOneByProjectAndUuid(Project $project, string $uuid): ?Issue
    {
        /** @var Issue|null $issue */
        $issue = $this->createQueryBuilder('i')
            ->leftJoin('i.assignee', 'assignee_user')->addSelect('assignee_user')
            ->leftJoin('i.duplicateOf', 'duplicate_of')->addSelect('duplicate_of')
            ->andWhere('i.project = :project')
            ->andWhere('i.uuid = :uuid')
            ->setParameter('project', $project)
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();

        return $issue;
    }

    /**
     * Issue detail load with assignee + duplicateOf + project (avoids N+1 on show).
     */
    public function findOneByUuidHydrated(string $uuid): ?Issue
    {
        /** @var Issue|null $issue */
        $issue = $this->createQueryBuilder('i')
            ->leftJoin('i.assignee', 'assignee_user')->addSelect('assignee_user')
            ->leftJoin('i.duplicateOf', 'duplicate_of')->addSelect('duplicate_of')
            ->leftJoin('i.project', 'p')->addSelect('p')
            ->andWhere('i.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();

        return $issue;
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
     * Open/unresolved (or other status) counts for many projects in one query.
     *
     * @param list<int> $projectIds
     *
     * @return array<int, int> project id => count
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

    /**
     * Similar issues in the same project (title proximity; excludes self; prefers non-ignored).
     *
     * @return list<Issue>
     */
    public function findSimilarIssues(Issue $issue, int $limit = 5): array
    {
        $project = $issue->getProject();
        if (!$project instanceof Project || $limit < 1) {
            return [];
        }

        $title = trim($issue->getTitle());
        if ('' === $title) {
            return [];
        }

        $tokens = preg_split('/\s+/u', $title) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            static fn (string $t): bool => mb_strlen($t) >= 3,
        ));
        if ([] === $tokens) {
            $tokens = [$title];
        }
        $tokens = \array_slice($tokens, 0, 4);

        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.id != :excludeId')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('project', $project)
            ->setParameter('excludeId', $issue->getId() ?? 0)
            ->setParameter('statuses', [IssueStatus::Unresolved, IssueStatus::Resolved])
            ->orderBy('i.lastSeen', 'DESC')
            ->setMaxResults($limit * 3);

        $ors = [];
        foreach ($tokens as $i => $token) {
            $param = 'sim'.$i;
            $ors[] = 'LOWER(i.title) LIKE :'.$param;
            $qb->setParameter($param, '%'.$this->escapeLike(mb_strtolower($token)).'%');
        }
        $qb->andWhere('('.implode(' OR ', $ors).')');

        /** @var list<Issue> $candidates */
        $candidates = $qb->getQuery()->getResult();

        $scored = [];
        $needle = mb_strtolower($title);
        foreach ($candidates as $candidate) {
            $other = mb_strtolower($candidate->getTitle());
            $score = 0;
            if ($other === $needle) {
                $score += 100;
            }
            foreach ($tokens as $token) {
                if (str_contains($other, mb_strtolower($token))) {
                    $score += 10;
                }
            }
            if ($score > 0) {
                $scored[] = ['issue' => $candidate, 'score' => $score, 'last' => $candidate->getLastSeen()->getTimestamp()];
            }
        }

        usort(
            $scored,
            static function (array $a, array $b): int {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }

                return $b['last'] <=> $a['last'];
            },
        );

        $out = [];
        foreach (\array_slice($scored, 0, $limit) as $row) {
            $out[] = $row['issue'];
        }

        return $out;
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

    /**
     * MySQL: FULLTEXT MATCH…AGAINST on title+culprit (BOOLEAN MODE).
     * SQLite / other platforms: LIKE fallback (tests and non-MySQL).
     *
     * MATCH…AGAINST is MySQL SQL, not DQL — Doctrine's parser rejects it as a
     * "known function". Resolve matching ids with native SQL, then restrict the
     * QueryBuilder (same pattern as tag/url payload filters).
     */
    private function applyFullTextOrLikeQuery(QueryBuilder $qb, string $query): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            $boolean = $this->toBooleanFulltextQuery($query);
            if ('' === $boolean) {
                $qb->andWhere('i.title LIKE :q OR i.culprit LIKE :q')
                    ->setParameter('q', '%'.$query.'%');

                return;
            }

            /** @var list<int|string> $rows */
            $rows = $conn->fetchFirstColumn(
                'SELECT id FROM issue WHERE MATCH(title, culprit) AGAINST (? IN BOOLEAN MODE)',
                [$boolean],
            );
            $ids = array_map(static fn (int|string $id): int => (int) $id, $rows);
            $this->restrictToIssueIds($qb, $ids, 'fulltextIssueIds');

            return;
        }

        $qb->andWhere('i.title LIKE :q OR i.culprit LIKE :q')
            ->setParameter('q', '%'.$query.'%');
    }

    /**
     * Build a conservative BOOLEAN MODE query (+token*) from free text.
     * Strips FULLTEXT operators; skips tokens shorter than 2 chars (InnoDB default min is often 3 —
     * shorter tokens still fall through LIKE if all tokens are dropped).
     */
    private function toBooleanFulltextQuery(string $query): string
    {
        $tokens = preg_split('/\s+/u', $query) ?: [];
        $parts = [];
        foreach ($tokens as $token) {
            $clean = preg_replace('/[+\-><()~*"@]+/u', '', $token) ?? '';
            $clean = trim($clean);
            if (\strlen($clean) < 2) {
                continue;
            }
            $parts[] = '+'.$clean.'*';
        }

        return implode(' ', $parts);
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
