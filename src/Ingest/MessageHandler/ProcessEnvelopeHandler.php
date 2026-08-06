<?php

declare(strict_types=1);

namespace App\Ingest\MessageHandler;

use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\Service\EnvelopeParser;
use App\Issues\Service\IssueEnvelopeWriter;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\VolumeThresholdEvaluator;
use App\Performance\Service\PerformanceEnvelopeWriter;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Orchestrates Envelope persistence: parse → domain writers → one flush → notify.
 *
 * Keep this handler thin: fingerprinting/grouping lives in {@see IssueEnvelopeWriter},
 * N+1 / spans in {@see PerformanceEnvelopeWriter}, outbound alerts in Notifications.
 * OTLP adapters map into {@see ProcessEnvelopeMessage} under `App\Ingest\Otlp`.
 */
#[AsMessageHandler]
final readonly class ProcessEnvelopeHandler
{
    public function __construct(
        private EnvelopeParser $envelopeParser,
        private ProjectRepository $projectRepository,
        private IssueEnvelopeWriter $issueEnvelopeWriter,
        private PerformanceEnvelopeWriter $performanceEnvelopeWriter,
        private NotificationDispatcher $notificationDispatcher,
        private VolumeThresholdEvaluator $volumeThresholdEvaluator,
        private EntityManagerInterface $entityManager,
        private ProjectGovernanceResolver $governanceResolver,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessEnvelopeMessage $message): void
    {
        // Sync Messenger shares the HTTP EntityManager. If Envelope auth left a
        // ProjectApiKey managed, clear so notification flushes cannot double-encrypt
        // Halite secrets. Skip clear when invoked without auth entities (unit-style tests).
        $identityMap = $this->entityManager->getUnitOfWork()->getIdentityMap();
        if ([] !== ($identityMap[ProjectApiKey::class] ?? [])) {
            $this->entityManager->clear();
        }

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

        if ($this->governanceResolver->isMonthlyQuotaExceeded($project)) {
            $this->logger->info('Dropping queued Envelope: monthly event quota exceeded.', [
                'project_id' => $message->projectId,
            ]);

            return;
        }

        $parsed = $this->envelopeParser->parse($message->rawEnvelope);
        $receivedAt = new DateTimeImmutable($message->receivedAtIso);

        /** @var list<callable(): void> $afterFlush */
        $afterFlush = [];
        /** @var array<string, array{0: ?string, 1: ?string}> $volumeThresholdKeys */
        $volumeThresholdKeys = [];

        foreach ($parsed['items'] as $item) {
            $type = (string) ($item['header']['type'] ?? '');
            $payload = $item['payload'];
            if (!\is_array($payload)) {
                continue;
            }

            if ('event' === $type) {
                $result = $this->issueEnvelopeWriter->write($project, $payload, $receivedAt);
                if ($result->skipped || null === $result->issue) {
                    continue;
                }

                $issue = $result->issue;
                $isNew = $result->isNew;
                $isRegression = $result->isRegression;
                $afterFlush[] = function () use ($project, $issue, $isNew, $isRegression): void {
                    if ($isNew) {
                        $this->notificationDispatcher->dispatchNewIssue($project, $issue);
                    } elseif ($isRegression) {
                        $this->notificationDispatcher->dispatchIssueRegression($project, $issue);
                    }
                };

                if ($result->countsTowardVolumeThreshold) {
                    $key = ($result->environment ?? '')."\0".($result->release ?? '');
                    $volumeThresholdKeys[$key] = [$result->environment, $result->release];
                }
            } elseif ('transaction' === $type) {
                $result = $this->performanceEnvelopeWriter->write($project, $payload, $receivedAt);
                if ($result->nPlusOneCount > 0) {
                    $tx = $result->transaction;
                    $afterFlush[] = function () use ($project, $tx): void {
                        $this->notificationDispatcher->dispatchNPlusOne($project, $tx);
                    };
                }
            }
            // Other item types are accepted at the HTTP layer and ignored here.
        }

        $this->entityManager->flush();

        foreach ($afterFlush as $callback) {
            $callback();
        }

        if ([] !== $volumeThresholdKeys) {
            $this->volumeThresholdEvaluator->evaluateContexts(
                $project,
                array_values($volumeThresholdKeys),
                $receivedAt,
            );
        }
    }
}
