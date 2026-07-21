<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Issues\Dto\IssueOccurrenceStats;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Shared\IssueStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function countReceivedSinceForProject(Project $project, DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->innerJoin('e.issue', 'i')
            ->andWhere('i.project = :project')
            ->andWhere('e.receivedAt >= :since')
            ->setParameter('project', $project)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLastReceivedAtForProject(Project $project): ?DateTimeImmutable
    {
        $value = $this->createQueryBuilder('e')
            ->select('MAX(e.receivedAt)')
            ->innerJoin('e.issue', 'i')
            ->andWhere('i.project = :project')
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
            ->andWhere('i.project = :project')
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
}
