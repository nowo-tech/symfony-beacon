<?php

declare(strict_types=1);

namespace App\Notifications\MessageHandler;

use App\Issues\Entity\Issue;
use App\Notifications\Message\DispatchIngestNotificationsMessage;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\VolumeThresholdEvaluator;
use App\Performance\Entity\PerfTransaction;
use App\Project\Repository\ProjectRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs outbound alert evaluation off the ingest worker path.
 */
#[AsMessageHandler(sign: true)]
final readonly class DispatchIngestNotificationsHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $entityManager,
        private NotificationDispatcher $notificationDispatcher,
        private VolumeThresholdEvaluator $volumeThresholdEvaluator,
    ) {
    }

    public function __invoke(DispatchIngestNotificationsMessage $message): void
    {
        $project = $this->projectRepository->find($message->projectId);
        if (null === $project) {
            return;
        }

        foreach ($message->alerts as $alert) {
            $kind = $alert['kind'] ?? '';
            if ('new' === $kind || 'regression' === $kind) {
                $issueId = $alert['issue_id'] ?? null;
                if (!\is_int($issueId)) {
                    continue;
                }
                $issue = $this->entityManager->find(Issue::class, $issueId);
                if (!$issue instanceof Issue) {
                    continue;
                }
                if ('new' === $kind) {
                    $this->notificationDispatcher->dispatchNewIssue($project, $issue);
                } else {
                    $this->notificationDispatcher->dispatchIssueRegression($project, $issue);
                }
            } elseif ('nplus1' === $kind) {
                $txId = $alert['transaction_id'] ?? null;
                if (!\is_int($txId)) {
                    continue;
                }
                $tx = $this->entityManager->find(PerfTransaction::class, $txId);
                if (!$tx instanceof PerfTransaction) {
                    continue;
                }
                $this->notificationDispatcher->dispatchNPlusOne($project, $tx);
            }
        }

        if ([] !== $message->volumeThresholdContexts) {
            $this->volumeThresholdEvaluator->evaluateContexts(
                $project,
                $message->volumeThresholdContexts,
                new DateTimeImmutable($message->receivedAtIso),
            );
        }
    }
}
