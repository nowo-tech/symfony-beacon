<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use App\Issues\Enum\IssueStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Merges a duplicate issue's events into a canonical issue and archives the source.
 */
final readonly class IssueMergeService
{
    public function __construct(
        private EventRepository $eventRepository,
        private IssueRepository $issueRepository,
        private IssueHistoryRecorder $historyRecorder,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Ensure $source may point at $canonical as duplicateOf (same project, no cycles).
     *
     * @throws InvalidArgumentException with codes: cannot_merge_self, wrong_project, circular
     */
    public function assertCanMarkAsDuplicate(Issue $source, Issue $canonical): void
    {
        if (null !== $source->getId() && $source->getId() === $canonical->getId()) {
            throw new InvalidArgumentException('cannot_merge_self');
        }
        if ($source->getUuid() === $canonical->getUuid()) {
            throw new InvalidArgumentException('cannot_merge_self');
        }
        if ($source->getProject()?->getId() !== $canonical->getProject()?->getId()) {
            throw new InvalidArgumentException('wrong_project');
        }

        $seen = [];
        $cursor = $canonical;
        while ($cursor instanceof Issue) {
            $id = $cursor->getId();
            if (null !== $id && $id === $source->getId()) {
                throw new InvalidArgumentException('circular');
            }
            if (null !== $id) {
                if (isset($seen[$id])) {
                    break;
                }
                $seen[$id] = true;
            }
            $cursor = $cursor->getDuplicateOf();
        }
    }

    /**
     * Move all events from $source onto $canonical, recompute aggregates, mark source ignored + duplicateOf.
     *
     * @throws InvalidArgumentException when merge is not allowed
     */
    public function mergeIntoCanonical(Issue $source, Issue $canonical, ?User $actor = null): int
    {
        $this->assertCanMarkAsDuplicate($source, $canonical);

        $sourceEvents = $this->eventRepository->findBy(['issue' => $source]);
        $canonicalEvents = $this->eventRepository->findBy(['issue' => $canonical]);
        $moved = 0;
        foreach ($sourceEvents as $event) {
            if (!$event instanceof Event) {
                continue;
            }
            $event->setIssue($canonical);
            ++$moved;
        }

        $source->setDuplicateOf($canonical);
        $previousStatus = $source->getStatus();
        $source->setStatus(IssueStatus::Ignored);
        $source->setEventCount(0);
        if (IssueStatus::Ignored !== $previousStatus) {
            $this->historyRecorder->recordStatusChange($source, $previousStatus, IssueStatus::Ignored, $actor);
        }

        /** @var list<Event> $combined */
        $combined = [];
        foreach ([...$canonicalEvents, ...$sourceEvents] as $event) {
            if ($event instanceof Event) {
                $combined[] = $event;
            }
        }
        $this->applyAggregatesFromEvents($canonical, $combined);
        $this->entityManager->flush();

        return $moved;
    }

    /**
     * Recalculate eventCount, first/last seen, and release/environment denormalized fields from events.
     *
     * Uses SQL aggregates so retention purge does not hydrate every event into PHP.
     */
    public function recomputeAggregates(Issue $issue): void
    {
        $issueId = $issue->getId();
        if (null === $issueId) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        $row = $connection->fetchAssociative(
            'SELECT COUNT(*) AS cnt, MIN(received_at) AS first_seen, MAX(received_at) AS last_seen FROM event WHERE issue_id = ?',
            [$issueId],
        );

        $count = (int) ($row['cnt'] ?? 0);
        $issue->setEventCount($count);

        if (0 === $count) {
            return;
        }

        $firstSeen = $row['first_seen'] ?? null;
        $lastSeen = $row['last_seen'] ?? null;
        if (\is_string($firstSeen) && '' !== $firstSeen) {
            $issue->setFirstSeen(new DateTimeImmutable($firstSeen));
        }
        if (\is_string($lastSeen) && '' !== $lastSeen) {
            $issue->setLastSeen(new DateTimeImmutable($lastSeen));
        }

        $firstRelease = $connection->fetchOne(
            'SELECT release_version FROM event WHERE issue_id = ? AND release_version IS NOT NULL AND TRIM(release_version) <> \'\' ORDER BY received_at ASC, id ASC LIMIT 1',
            [$issueId],
        );
        if (\is_string($firstRelease) && '' !== trim($firstRelease)) {
            $issue->setFirstRelease(Issue::normalizeRelease($firstRelease));
        }

        $lastMeta = $connection->fetchAssociative(
            'SELECT release_version, environment FROM event WHERE issue_id = ? ORDER BY received_at DESC, id DESC LIMIT 1',
            [$issueId],
        );
        if (\is_array($lastMeta)) {
            $lastRelease = $lastMeta['release_version'] ?? null;
            if (\is_string($lastRelease) && '' !== trim($lastRelease)) {
                $issue->setLastRelease(Issue::normalizeRelease($lastRelease));
            }
            $lastEnv = $lastMeta['environment'] ?? null;
            if (\is_string($lastEnv) && '' !== trim($lastEnv)) {
                $issue->setLastEnvironment(Issue::normalizeEnvironment($lastEnv));
            }
        }
    }

    /**
     * Recompute denormalized counters for every remaining issue in a project (after retention purge).
     *
     * Uses grouped SQL so retention does not run ~4 queries per issue.
     */
    public function recomputeAggregatesForProject(Project $project): int
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return 0;
        }

        $connection = $this->entityManager->getConnection();

        /** @var list<array{issue_id: int|string, cnt: int|string, first_seen: ?string, last_seen: ?string}> $aggregates */
        $aggregates = $connection->fetchAllAssociative(
            'SELECT i.id AS issue_id, COUNT(e.id) AS cnt, MIN(e.received_at) AS first_seen, MAX(e.received_at) AS last_seen
             FROM issue i
             LEFT JOIN event e ON e.issue_id = i.id
             WHERE i.project_id = ?
             GROUP BY i.id',
            [$projectId],
        );

        if ([] === $aggregates) {
            return 0;
        }

        $issueIds = [];
        foreach ($aggregates as $row) {
            $issueIds[] = (int) $row['issue_id'];
        }

        $firstReleases = $this->fetchFirstReleasesByIssueIds($issueIds);
        $lastMeta = $this->fetchLastMetaByIssueIds($issueIds);

        $issues = $this->issueRepository->findBy(['id' => $issueIds]);
        $byId = [];
        foreach ($issues as $issue) {
            $id = $issue->getId();
            if (null !== $id) {
                $byId[$id] = $issue;
            }
        }

        $updated = 0;
        foreach ($aggregates as $row) {
            $issueId = (int) $row['issue_id'];
            $issue = $byId[$issueId] ?? null;
            if (!$issue instanceof Issue) {
                continue;
            }

            $count = (int) $row['cnt'];
            $issue->setEventCount($count);

            if ($count > 0) {
                $firstSeen = $row['first_seen'] ?? null;
                $lastSeen = $row['last_seen'] ?? null;
                if (\is_string($firstSeen) && '' !== $firstSeen) {
                    $issue->setFirstSeen(new DateTimeImmutable($firstSeen));
                }
                if (\is_string($lastSeen) && '' !== $lastSeen) {
                    $issue->setLastSeen(new DateTimeImmutable($lastSeen));
                }

                $firstRelease = $firstReleases[$issueId] ?? null;
                if (\is_string($firstRelease) && '' !== trim($firstRelease)) {
                    $issue->setFirstRelease(Issue::normalizeRelease($firstRelease));
                }

                $meta = $lastMeta[$issueId] ?? null;
                if (\is_array($meta)) {
                    $lastRelease = $meta['release_version'] ?? null;
                    if (\is_string($lastRelease) && '' !== trim($lastRelease)) {
                        $issue->setLastRelease(Issue::normalizeRelease($lastRelease));
                    }
                    $lastEnv = $meta['environment'] ?? null;
                    if (\is_string($lastEnv) && '' !== trim($lastEnv)) {
                        $issue->setLastEnvironment(Issue::normalizeEnvironment($lastEnv));
                    }
                }
            }

            ++$updated;
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }

        return $updated;
    }

    /**
     * @param list<int> $issueIds
     *
     * @return array<int, string>
     */
    private function fetchFirstReleasesByIssueIds(array $issueIds): array
    {
        if ([] === $issueIds) {
            return [];
        }

        $connection = $this->entityManager->getConnection();
        $placeholders = implode(',', array_fill(0, \count($issueIds), '?'));

        /** @var list<array{issue_id: int|string, release_version: string}> $rows */
        $rows = $connection->fetchAllAssociative(
            "SELECT issue_id, release_version FROM (
                SELECT issue_id, release_version,
                       ROW_NUMBER() OVER (PARTITION BY issue_id ORDER BY received_at ASC, id ASC) AS rn
                FROM event
                WHERE issue_id IN ($placeholders)
                  AND release_version IS NOT NULL
                  AND TRIM(release_version) <> ''
             ) ranked WHERE rn = 1",
            $issueIds,
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['issue_id']] = $row['release_version'];
        }

        return $map;
    }

    /**
     * @param list<int> $issueIds
     *
     * @return array<int, array{release_version: ?string, environment: ?string}>
     */
    private function fetchLastMetaByIssueIds(array $issueIds): array
    {
        if ([] === $issueIds) {
            return [];
        }

        $connection = $this->entityManager->getConnection();
        $placeholders = implode(',', array_fill(0, \count($issueIds), '?'));

        /** @var list<array{issue_id: int|string, release_version: ?string, environment: ?string}> $rows */
        $rows = $connection->fetchAllAssociative(
            "SELECT issue_id, release_version, environment FROM (
                SELECT issue_id, release_version, environment,
                       ROW_NUMBER() OVER (PARTITION BY issue_id ORDER BY received_at DESC, id DESC) AS rn
                FROM event
                WHERE issue_id IN ($placeholders)
             ) ranked WHERE rn = 1",
            $issueIds,
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['issue_id']] = [
                'release_version' => $row['release_version'],
                'environment' => $row['environment'],
            ];
        }

        return $map;
    }

    /**
     * @param list<Event> $events
     */
    private function applyAggregatesFromEvents(Issue $issue, array $events): void
    {
        usort(
            $events,
            static fn (Event $a, Event $b): int => $a->getReceivedAt() <=> $b->getReceivedAt(),
        );

        $count = \count($events);
        $issue->setEventCount($count);

        if (0 === $count) {
            return;
        }

        $first = $events[0];
        $last = $events[$count - 1];

        $issue->setFirstSeen($first->getReceivedAt());
        $issue->setLastSeen($last->getReceivedAt());

        $firstRelease = null;
        foreach ($events as $event) {
            $release = $event->getReleaseVersion();
            if (\is_string($release) && '' !== trim($release)) {
                $firstRelease = Issue::normalizeRelease($release);
                break;
            }
        }
        if (null !== $firstRelease) {
            $issue->setFirstRelease($firstRelease);
        }

        $lastRelease = $last->getReleaseVersion();
        if (\is_string($lastRelease) && '' !== trim($lastRelease)) {
            $issue->setLastRelease(Issue::normalizeRelease($lastRelease));
        }

        $lastEnv = $last->getEnvironment();
        if (\is_string($lastEnv) && '' !== trim($lastEnv)) {
            $issue->setLastEnvironment(Issue::normalizeEnvironment($lastEnv));
        }
    }
}
