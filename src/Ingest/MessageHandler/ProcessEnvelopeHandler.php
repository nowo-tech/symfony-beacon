<?php

declare(strict_types=1);

namespace App\Ingest\MessageHandler;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\Service\EnvelopeParser;
use App\Ingest\Service\EventTimestampParser;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\FingerprintCalculator;
use App\Issues\Service\IssueHistoryRecorder;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\VolumeThresholdEvaluator;
use App\Performance\Entity\PerfSpan;
use App\Performance\Entity\PerfTransaction;
use App\Performance\Service\NPlusOneDetector;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use App\Shared\IssueStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Persists Envelope event/transaction items, groups issues, and updates analytics.
 */
#[AsMessageHandler]
final readonly class ProcessEnvelopeHandler
{
    public function __construct(
        private EnvelopeParser $envelopeParser,
        private EventTimestampParser $eventTimestampParser,
        private FingerprintCalculator $fingerprintCalculator,
        private NPlusOneDetector $nPlusOneDetector,
        private ProjectRepository $projectRepository,
        private IssueRepository $issueRepository,
        private EventRepository $eventRepository,
        private DailyProjectStatRepository $dailyProjectStatRepository,
        private NotificationDispatcher $notificationDispatcher,
        private VolumeThresholdEvaluator $volumeThresholdEvaluator,
        private IssueHistoryRecorder $historyRecorder,
        private EntityManagerInterface $entityManager,
        private ProjectGovernanceResolver $governanceResolver,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessEnvelopeMessage $message): void
    {
        $project = $this->projectRepository->find($message->projectId);
        if (null === $project) {
            return;
        }

        // Re-check governance after HTTP ACK (project may have been suspended / quota hit while queued).
        if (!$project->isIngestEnabled()) {
            $this->logger->info('Dropping queued Envelope: ingest disabled.', [
                'project_id' => $message->projectId,
            ]);

            return;
        }

        if ($this->governanceResolver->isDailyQuotaExceeded($project)) {
            $this->logger->info('Dropping queued Envelope: daily event quota exceeded.', [
                'project_id' => $message->projectId,
            ]);

            return;
        }

        $parsed = $this->envelopeParser->parse($message->rawEnvelope);
        $receivedAt = new DateTimeImmutable($message->receivedAtIso);

        foreach ($parsed['items'] as $item) {
            $type = (string) ($item['header']['type'] ?? '');
            $payload = $item['payload'];
            if (!\is_array($payload)) {
                continue;
            }

            if ('event' === $type) {
                $this->ingestEvent($project->getId() ?? 0, $payload, $receivedAt);
            } elseif ('transaction' === $type) {
                $this->ingestTransaction($project->getId() ?? 0, $payload, $receivedAt);
            }
            // Other item types are accepted at the HTTP layer and ignored here.
        }

        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function ingestEvent(int $projectId, array $payload, DateTimeImmutable $receivedAt): void
    {
        $project = $this->projectRepository->find($projectId);
        if (null === $project) {
            return;
        }

        $eventId = (string) ($payload['event_id'] ?? bin2hex(random_bytes(16)));
        if ($this->eventRepository->findOneByEventId($eventId) instanceof Event) {
            return;
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

        $this->entityManager->flush();

        if ($isNew) {
            $this->notificationDispatcher->dispatchNewIssue($project, $issue);
        } elseif ($isRegression) {
            $this->notificationDispatcher->dispatchIssueRegression($project, $issue);
        }

        $level = strtolower((string) ($payload['level'] ?? 'error'));
        if (\in_array($level, ['error', 'fatal'], true)) {
            $this->volumeThresholdEvaluator->evaluate(
                $project,
                isset($payload['environment']) ? (string) $payload['environment'] : null,
                isset($payload['release']) ? (string) $payload['release'] : null,
                $receivedAt,
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function ingestTransaction(int $projectId, array $payload, DateTimeImmutable $receivedAt): void
    {
        $project = $this->projectRepository->find($projectId);
        if (null === $project) {
            return;
        }

        $spansRaw = $payload['spans'] ?? [];
        $spanInputs = [];
        if (\is_array($spansRaw)) {
            foreach ($spansRaw as $span) {
                if (!\is_array($span)) {
                    continue;
                }
                $spanInputs[] = [
                    'op' => (string) ($span['op'] ?? ''),
                    'description' => (string) ($span['description'] ?? ''),
                    'span_id' => (string) ($span['span_id'] ?? ''),
                    'start_timestamp' => $span['start_timestamp'] ?? null,
                    'timestamp' => $span['timestamp'] ?? null,
                ];
            }
        }

        $detection = $this->nPlusOneDetector->detect($spanInputs);
        $candidateIds = array_fill_keys($detection['candidate_span_ids'], true);

        $tx = new PerfTransaction();
        $tx->setProject($project);
        $tx->setEventId((string) ($payload['event_id'] ?? bin2hex(random_bytes(16))));
        $tx->setTransactionName((string) ($payload['transaction'] ?? $payload['transaction_info']['name'] ?? 'unknown'));
        $tx->setPayload($payload);
        $tx->setReceivedAt($receivedAt);
        $tx->setSpanCount(\count($spanInputs));
        $tx->setNPlusOneCount($detection['count']);

        $start = isset($payload['start_timestamp']) && is_numeric($payload['start_timestamp']) ? (float) $payload['start_timestamp'] : null;
        $end = isset($payload['timestamp']) && is_numeric($payload['timestamp']) ? (float) $payload['timestamp'] : null;
        if (null !== $start && null !== $end) {
            $tx->setDurationMs(max(0, ($end - $start) * 1000));
        }

        foreach ($spanInputs as $spanData) {
            $span = new PerfSpan();
            $span->setSpanId('' !== $spanData['span_id'] ? $spanData['span_id'] : bin2hex(random_bytes(8)));
            $span->setOp($spanData['op']);
            $span->setDescription($spanData['description']);
            $span->setNPlusOneCandidate(isset($candidateIds[$spanData['span_id']]));
            $s = isset($spanData['start_timestamp']) && is_numeric($spanData['start_timestamp']) ? (float) $spanData['start_timestamp'] : null;
            $e = isset($spanData['timestamp']) && is_numeric($spanData['timestamp']) ? (float) $spanData['timestamp'] : null;
            if (null !== $s && null !== $e) {
                $span->setDurationMs(max(0, ($e - $s) * 1000));
            }
            $tx->addSpan($span);
        }

        $this->entityManager->persist($tx);

        $stat = $this->dailyProjectStatRepository->findOrCreate($project, $receivedAt);
        $stat->incrementTransactionCount();
        if ($detection['count'] > 0) {
            $stat->incrementNPlusOneCount($detection['count']);
        }

        $this->entityManager->flush();

        if ($detection['count'] > 0) {
            $this->notificationDispatcher->dispatchNPlusOne($project, $tx);
        }
    }

    /**
     * Updates denormalized first/last release and last environment on the issue.
     *
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
