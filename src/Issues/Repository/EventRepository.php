<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Issues\Dto\IssueOccurrenceStats;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Shared\IssueStatus;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @return list<Event>
     */
    public function findLatestForIssue(Issue $issue, int $limit = 50): array
    {
        /** @var list<Event> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.issue = :issue')
            ->setParameter('issue', $issue)
            ->orderBy('e.receivedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findOneByEventId(string $eventId): ?Event
    {
        return $this->findOneBy(['eventId' => $eventId]);
    }

    public function findOneByProjectAndEventId(Project $project, string $eventId): ?Event
    {
        return $this->findOneBy(['project' => $project, 'eventId' => $eventId]);
    }

    public function occurrenceStatsForIssue(Issue $issue, ?DateTimeImmutable $now = null): IssueOccurrenceStats
    {
        $map = $this->occurrenceStatsForIssues([$issue], $now);

        return $map[$issue->getId()] ?? new IssueOccurrenceStats(
            total: $issue->getEventCount(),
            last24h: 0,
            last7d: 0,
            last30d: 0,
        );
    }

    /**
     * Batch window counts for issue list pages (avoids N+1).
     *
     * @param list<Issue> $issues
     *
     * @return array<int, IssueOccurrenceStats> keyed by issue id
     */
    public function occurrenceStatsForIssues(array $issues, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $stats = [];
        foreach ($issues as $issue) {
            $id = $issue->getId();
            if (null === $id) {
                continue;
            }
            $stats[$id] = new IssueOccurrenceStats(
                total: $issue->getEventCount(),
                last24h: 0,
                last7d: 0,
                last30d: 0,
            );
        }

        if ([] === $stats) {
            return [];
        }

        $since30 = $now->modify('-30 days');
        $since7 = $now->modify('-7 days');
        $since24 = $now->modify('-24 hours');

        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.issue) AS issueId')
            ->addSelect('SUM(CASE WHEN e.receivedAt >= :since24 THEN 1 ELSE 0 END) AS c24')
            ->addSelect('SUM(CASE WHEN e.receivedAt >= :since7 THEN 1 ELSE 0 END) AS c7')
            ->addSelect('SUM(CASE WHEN e.receivedAt >= :since30 THEN 1 ELSE 0 END) AS c30')
            ->andWhere('e.issue IN (:issues)')
            ->andWhere('e.receivedAt >= :since30')
            ->setParameter('issues', $issues)
            ->setParameter('since24', $since24)
            ->setParameter('since7', $since7)
            ->setParameter('since30', $since30)
            ->groupBy('e.issue')
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $id = (int) $row['issueId'];
            if (!isset($stats[$id])) {
                continue;
            }
            $stats[$id] = new IssueOccurrenceStats(
                total: $stats[$id]->total,
                last24h: (int) $row['c24'],
                last7d: (int) $row['c7'],
                last30d: (int) $row['c30'],
            );
        }

        return $stats;
    }

    public function countReceivedTodayForProject(Project $project): int
    {
        $start = new DateTimeImmutable('today');

        return $this->countReceivedSinceForProject($project, $start);
    }

    /**
     * Distinct release versions seen on events for a project, newest first.
     *
     * @return list<string>
     */
    public function findDistinctReleaseVersions(Project $project): array
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT e.release_version AS release_value
            FROM event e
            WHERE e.project_id = :projectId
              AND e.release_version IS NOT NULL
              AND e.release_version <> ''
            GROUP BY e.release_version
            ORDER BY MAX(e.received_at) DESC, e.release_version DESC
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

    public function countReceivedSinceForProject(Project $project, DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.project = :project')
            ->andWhere('e.receivedAt >= :since')
            ->setParameter('project', $project)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<int> $projectIds
     *
     * @return array<int, int> project id => event count since $since
     */
    public function countReceivedSinceForProjectIds(array $projectIds, DateTimeImmutable $since): array
    {
        if ([] === $projectIds) {
            return [];
        }

        /** @var list<array{projectId: int|string, cnt: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.project) AS projectId, COUNT(e.id) AS cnt')
            ->andWhere('e.project IN (:projects)')
            ->andWhere('e.receivedAt >= :since')
            ->setParameter('projects', $projectIds)
            ->setParameter('since', $since)
            ->groupBy('e.project')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['projectId']] = (int) $row['cnt'];
        }

        return $map;
    }

    public function findLastReceivedAtForProject(Project $project): ?DateTimeImmutable
    {
        $value = $this->createQueryBuilder('e')
            ->select('MAX(e.receivedAt)')
            ->andWhere('e.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();

        if (null === $value || '' === $value) {
            return null;
        }

        return $value instanceof DateTimeImmutable
            ? $value
            : new DateTimeImmutable((string) $value);
    }

    /**
     * @param list<int> $projectIds
     *
     * @return array<int, DateTimeImmutable> project id => last received at
     */
    public function findLastReceivedAtForProjectIds(array $projectIds): array
    {
        if ([] === $projectIds) {
            return [];
        }

        /** @var list<array{projectId: int|string, lastAt: mixed}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.project) AS projectId, MAX(e.receivedAt) AS lastAt')
            ->andWhere('e.project IN (:projects)')
            ->setParameter('projects', $projectIds)
            ->groupBy('e.project')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $value = $row['lastAt'];
            if (null === $value || '' === $value) {
                continue;
            }
            $map[(int) $row['projectId']] = $value instanceof DateTimeImmutable
                ? $value
                : new DateTimeImmutable((string) $value);
        }

        return $map;
    }

    public function countReceivedSince(
        Project $project,
        DateTimeImmutable $since,
        ?string $environment,
        ?string $release,
    ): int {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->innerJoin('e.issue', 'i')
            ->andWhere('e.project = :project')
            ->andWhere('i.level IN (:levels)')
            ->andWhere('e.receivedAt >= :since')
            ->setParameter('project', $project)
            ->setParameter('levels', ['error', 'fatal'])
            ->setParameter('since', $since);

        if (null !== $environment && '' !== $environment) {
            $qb->andWhere('e.environment = :environment')
                ->setParameter('environment', $environment);
        }

        if (null !== $release && '' !== trim($release)) {
            $qb->andWhere('e.releaseVersion = :release')
                ->setParameter('release', trim($release));
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Filtered events for project export (joins issue; no raw payload).
     *
     * @return list<Event>
     */
    public function searchForExport(
        Project $project,
        ?string $query = null,
        ?string $level = null,
        ?IssueStatus $status = null,
        ?string $environment = null,
        ?string $release = null,
        int $limit = 1000,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.issue', 'i')
            ->addSelect('i')
            ->andWhere('e.project = :project')
            ->setParameter('project', $project)
            ->orderBy('e.receivedAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults(max(1, $limit));

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('i.title LIKE :q OR i.culprit LIKE :q OR e.eventId LIKE :q')
                ->setParameter('q', '%'.trim($query).'%');
        }
        if (null !== $level && '' !== $level) {
            $qb->andWhere('i.level = :level')->setParameter('level', $level);
        }
        if ($status instanceof IssueStatus) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }
        if (null !== $environment && '' !== $environment) {
            $qb->andWhere('e.environment = :env')->setParameter('env', $environment);
        }
        if (null !== $release && '' !== trim($release)) {
            $qb->andWhere('e.releaseVersion = :release')->setParameter('release', trim($release));
        }

        /** @var list<Event> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Daily error (event) counts for Analytics filters. UTC calendar days.
     *
     * @return array<string, int> keyed by Y-m-d
     */
    public function countErrorsByDay(
        Project $project,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        ?string $environment = null,
        ?string $release = null,
        ?string $level = null,
    ): array {
        $projectId = $project->getId();
        if (null === $projectId) {
            return [];
        }

        $fromStart = $from->setTime(0, 0);
        $toExclusive = $to->setTime(0, 0)->modify('+1 day');

        $sql = <<<'SQL'
            SELECT DATE(e.received_at) AS day_key, COUNT(e.id) AS cnt
            FROM event e
            INNER JOIN issue i ON e.issue_id = i.id
            WHERE e.project_id = :projectId
              AND e.received_at >= :fromStart
              AND e.received_at < :toExclusive
            SQL;

        $params = [
            'projectId' => $projectId,
            'fromStart' => $fromStart->format('Y-m-d H:i:s.u'),
            'toExclusive' => $toExclusive->format('Y-m-d H:i:s.u'),
        ];
        $types = [
            'projectId' => ParameterType::INTEGER,
            'fromStart' => ParameterType::STRING,
            'toExclusive' => ParameterType::STRING,
        ];

        if (null !== $environment && '' !== $environment) {
            $sql .= ' AND e.environment = :environment';
            $params['environment'] = $environment;
            $types['environment'] = ParameterType::STRING;
        }
        if (null !== $release && '' !== trim($release)) {
            $sql .= ' AND e.release_version = :release';
            $params['release'] = trim($release);
            $types['release'] = ParameterType::STRING;
        }
        if (null !== $level && '' !== $level) {
            $sql .= ' AND i.level = :level';
            $params['level'] = $level;
            $types['level'] = ParameterType::STRING;
        }

        $sql .= ' GROUP BY day_key ORDER BY day_key ASC';

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, $params, $types);

        $map = [];
        foreach ($rows as $row) {
            $key = (string) ($row['day_key'] ?? '');
            if ('' === $key) {
                continue;
            }
            // MySQL may return DateTime objects depending on platform config.
            if ($row['day_key'] instanceof DateTimeInterface) {
                $key = $row['day_key']->format('Y-m-d');
            }
            $map[$key] = (int) $row['cnt'];
        }

        return $map;
    }
}
