<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists Envelope event items into Issue + Event and bumps daily error counters.
 *
 * Called from {@see \App\Ingest\MessageHandler\ProcessEnvelopeHandler}; does not flush
 * or dispatch notifications — the handler owns the single flush and after-flush side effects.
 */
final readonly class IssueEnvelopeWriter
{
    public function __construct(
        private FingerprintCalculator $fingerprintCalculator,
        private EventTimestampParser $eventTimestampParser,
        private IssueRepository $issueRepository,
        private EventRepository $eventRepository,
        private DailyProjectStatRepository $dailyProjectStatRepository,
        private IssueHistoryRecorder $historyRecorder,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function write(
        Project $project,
        array $payload,
        DateTimeImmutable $receivedAt,
    ): IssueEnvelopeWriteResult {
        $eventId = (string) ($payload['event_id'] ?? bin2hex(random_bytes(16)));
        if ($this->eventRepository->findOneByProjectAndEventId($project, $eventId) instanceof Event) {
            return IssueEnvelopeWriteResult::skipped();
        }

        $fingerprint = $this->fingerprintCalculator->calculate($payload);
        $issue = $this->issueRepository->findOneByProjectAndFingerprint($project, $fingerprint);
        $isNew = !$issue instanceof Issue;
        $previousStatus = $issue instanceof Issue ? $issue->getStatus() : null;

        if ($isNew) {
            $issue = new Issue();
            $issue->setProject($project);
            $issue->setFingerprint($fingerprint);
            $issue->setTitle($this->fingerprintCalculator->title($payload));
            $issue->setCulprit($this->fingerprintCalculator->culprit($payload));
            $issue->setLevel((string) ($payload['level'] ?? 'error'));
            $issue->setFirstSeen($receivedAt);
            $this->entityManager->persist($issue);
        }

        $issue->setLastSeen($receivedAt);
        $issue->incrementEventCount();
        $issue->setTitle($this->fingerprintCalculator->title($payload));
        $issue->setCulprit($this->fingerprintCalculator->culprit($payload));
        $issue->setLevel((string) ($payload['level'] ?? $issue->getLevel()));

        $isRegression = false;
        if (!$isNew && (
            IssueStatus::Resolved === $previousStatus
            || IssueStatus::Ignored === $previousStatus
        )) {
            $issue->setStatus(IssueStatus::Unresolved);
            $this->historyRecorder->recordStatusChange($issue, $previousStatus, IssueStatus::Unresolved, null);
            $isRegression = true;
        }

        $event = new Event();
        $event->setIssue($issue);
        $event->setProject($project);
        $event->setEventId($eventId);
        $event->setPayload($payload);
        $event->setEnvironment(isset($payload['environment']) ? (string) $payload['environment'] : null);
        $event->setReleaseVersion(isset($payload['release']) ? (string) $payload['release'] : null);
        $event->setPlatform(isset($payload['platform']) ? (string) $payload['platform'] : 'php');
        $event->setPhpVersion($this->extractPhpVersion($payload));
        $event->setSymfonyVersion($this->extractSymfonyVersion($payload));
        $event->setUserIdentifier($this->extractUserIdentifier($payload));
        $event->setReceivedAt($receivedAt);
        $eventTimestamp = $this->eventTimestampParser->parse($payload['timestamp'] ?? ($payload['datetime'] ?? null));
        $event->setEventTimestamp($eventTimestamp ?? $receivedAt);
        $this->entityManager->persist($event);

        $this->applyReleaseContext($issue, $payload);

        $stat = $this->dailyProjectStatRepository->findOrCreate($project, $receivedAt);
        $stat->incrementErrorCount();

        $environment = isset($payload['environment']) ? (string) $payload['environment'] : null;
        $release = isset($payload['release']) ? (string) $payload['release'] : null;
        $level = strtolower((string) ($payload['level'] ?? 'error'));
        $countsTowardVolume = \in_array($level, ['error', 'fatal'], true);

        return new IssueEnvelopeWriteResult(
            skipped: false,
            issue: $issue,
            isNew: $isNew,
            isRegression: $isRegression,
            environment: $environment,
            release: $release,
            countsTowardVolumeThreshold: $countsTowardVolume,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyReleaseContext(Issue $issue, array $payload): void
    {
        $release = isset($payload['release']) && \is_scalar($payload['release'])
            ? Issue::normalizeRelease((string) $payload['release'])
            : null;
        $environment = isset($payload['environment']) && \is_scalar($payload['environment'])
            ? Issue::normalizeEnvironment((string) $payload['environment'])
            : null;

        if (null !== $release) {
            if (null === $issue->getFirstRelease() || '' === $issue->getFirstRelease()) {
                $issue->setFirstRelease($release);
            }
            $issue->setLastRelease($release);
        }

        if (null !== $environment) {
            $issue->setLastEnvironment($environment);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractPhpVersion(array $payload): ?string
    {
        $runtime = $payload['contexts']['runtime'] ?? null;
        if (\is_array($runtime) && isset($runtime['version']) && \is_scalar($runtime['version'])) {
            return substr((string) $runtime['version'], 0, 40);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractSymfonyVersion(array $payload): ?string
    {
        $framework = $payload['contexts']['framework'] ?? null;
        if (\is_array($framework)
            && isset($framework['name'], $framework['version'])
            && 'symfony' === strtolower((string) $framework['name'])
            && \is_scalar($framework['version'])
        ) {
            return substr((string) $framework['version'], 0, 40);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractUserIdentifier(array $payload): ?string
    {
        $user = $payload['user'] ?? null;
        if (!\is_array($user)) {
            return null;
        }

        foreach (['id', 'username', 'email'] as $key) {
            if (isset($user[$key]) && \is_scalar($user[$key]) && '' !== (string) $user[$key]) {
                return substr((string) $user[$key], 0, 180);
            }
        }

        return null;
    }
}
