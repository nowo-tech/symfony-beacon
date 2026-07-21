<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Repository\EventRepository;
use App\Shared\IssueStatus;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Merges a duplicate issue's events into a canonical issue and archives the source.
 */
final readonly class IssueMergeService
{
    public function __construct(
        private EventRepository $eventRepository,
        private IssueHistoryRecorder $historyRecorder,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Move all events from $source onto $canonical, recompute aggregates, mark source ignored + duplicateOf.
     *
     * @throws InvalidArgumentException when merge is not allowed
     */
    public function mergeIntoCanonical(Issue $source, Issue $canonical, ?User $actor = null): int
    {
        if ($source->getId() === $canonical->getId()) {
            throw new InvalidArgumentException('cannot_merge_self');
        }
        if ($source->getProject()?->getId() !== $canonical->getProject()?->getId()) {
            throw new InvalidArgumentException('wrong_project');
        }
        if ($canonical->getDuplicateOf()?->getId() === $source->getId()) {
            throw new InvalidArgumentException('circular');
        }

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
     */
    public function recomputeAggregates(Issue $issue): void
    {
        /** @var list<Event> $events */
        $events = [];
        foreach ($this->eventRepository->findBy(['issue' => $issue], ['receivedAt' => 'ASC']) as $event) {
            if ($event instanceof Event) {
                $events[] = $event;
            }
        }
        $this->applyAggregatesFromEvents($issue, $events);
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
