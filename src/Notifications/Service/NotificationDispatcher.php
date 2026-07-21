<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Entity\ProjectThresholdRule;
use App\Notifications\Message\DeliverNotificationMessage;
use App\Notifications\NotificationCategories;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Performance\Entity\PerfTransaction;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues outbound notifications for matching enabled destinations (never blocks Envelope ACK).
 * During quiet hours, buffers instead of immediate Messenger dispatch (except send-test).
 */
final readonly class NotificationDispatcher
{
    public function __construct(
        private NotificationDestinationRepository $destinationRepository,
        private NotificationDigestBufferRepository $bufferRepository,
        private NotificationPayloadBuilder $payloadBuilder,
        private QuietHoursEvaluator $quietHoursEvaluator,
        private MessageBusInterface $bus,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function dispatchNewIssue(Project $project, Issue $issue): void
    {
        $this->dispatchIssuePayload(
            $project,
            $issue->getLevel(),
            $this->payloadBuilder->forNewIssue($project, $issue),
        );
    }

    public function dispatchIssueRegression(Project $project, Issue $issue): void
    {
        $this->dispatchIssuePayload(
            $project,
            $issue->getLevel(),
            $this->payloadBuilder->forIssueRegression($project, $issue),
        );
    }

    public function dispatchIssueResolved(Project $project, Issue $issue): void
    {
        $this->dispatchCategoryPayload(
            $project,
            NotificationCategories::ISSUE_RESOLVED,
            $this->payloadBuilder->forIssueResolved($project, $issue),
        );
    }

    public function dispatchIssueReopened(Project $project, Issue $issue): void
    {
        $this->dispatchCategoryPayload(
            $project,
            NotificationCategories::ISSUE_REOPENED,
            $this->payloadBuilder->forIssueReopened($project, $issue),
        );
    }

    public function dispatchIssueAssigned(
        Project $project,
        Issue $issue,
        ?User $previousAssignee,
        ?User $newAssignee,
    ): void {
        $this->dispatchCategoryPayload(
            $project,
            NotificationCategories::ISSUE_ASSIGNED,
            $this->payloadBuilder->forIssueAssigned($project, $issue, $previousAssignee, $newAssignee),
        );
    }

    public function dispatchIssueCommented(Project $project, Issue $issue, IssueComment $comment): void
    {
        $this->dispatchCategoryPayload(
            $project,
            NotificationCategories::ISSUE_COMMENTED,
            $this->payloadBuilder->forIssueCommented($project, $issue, $comment),
        );
    }

    public function dispatchIssueDuplicated(Project $project, Issue $issue, Issue $canonical): void
    {
        $this->dispatchCategoryPayload(
            $project,
            NotificationCategories::ISSUE_DUPLICATED,
            $this->payloadBuilder->forIssueDuplicated($project, $issue, $canonical),
        );
    }

    public function dispatchNPlusOne(Project $project, PerfTransaction $transaction): void
    {
        if ($transaction->getNPlusOneCount() < 1) {
            return;
        }

        $payload = $this->payloadBuilder->forNPlusOne($project, $transaction);
        foreach ($this->destinationRepository->findEnabledByProject($project) as $destination) {
            if (!$destination->matchesCategory(NotificationCategories::N_PLUS_ONE)) {
                continue;
            }
            $this->enqueueOrBuffer($destination->getId() ?? 0, $destination, $payload);
        }
        $this->entityManager->flush();
    }

    public function dispatchVolumeThreshold(Project $project, ProjectThresholdRule $rule, int $actualCount): void
    {
        $this->dispatchCategoryPayload(
            $project,
            NotificationCategories::VOLUME_THRESHOLD,
            $this->payloadBuilder->forVolumeThreshold($project, $rule, $actualCount),
        );
    }

    public function dispatchTest(Project $project, NotificationDestination $destination): void
    {
        $id = $destination->getId();
        if (null === $id) {
            return;
        }

        $payload = $this->payloadBuilder->forTest(
            $project,
            $destination->getLabel(),
            $destination->getType(),
        );
        // Send-test always bypasses quiet hours and category filters.
        $this->bus->dispatch(new DeliverNotificationMessage($id, $payload));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatchIssuePayload(Project $project, string $level, array $payload): void
    {
        $level = strtolower($level);
        if (!NotificationCategories::isIssueLevel($level)) {
            $level = 'error';
            $payload['category'] = $level;
        }

        $this->dispatchCategoryPayload($project, $level, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatchCategoryPayload(Project $project, string $category, array $payload): void
    {
        foreach ($this->destinationRepository->findEnabledByProject($project) as $destination) {
            if (!$destination->matchesCategory($category)) {
                continue;
            }
            $id = $destination->getId();
            if (null === $id) {
                continue;
            }
            $this->enqueueOrBuffer($id, $destination, $payload);
        }
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function enqueueOrBuffer(int $destinationId, NotificationDestination $destination, array $payload): void
    {
        if ($destinationId < 1) {
            return;
        }

        if ($this->quietHoursEvaluator->isQuietHoursActive($destination)) {
            $this->bufferRepository->buffer($destination, $payload);

            return;
        }

        $this->bus->dispatch(new DeliverNotificationMessage($destinationId, $payload));
    }
}
