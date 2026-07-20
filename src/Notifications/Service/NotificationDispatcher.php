<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Issues\Entity\Issue;
use App\Notifications\Message\DeliverNotificationMessage;
use App\Notifications\NotificationCategories;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Performance\Entity\PerfTransaction;
use App\Project\Entity\Project;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues outbound notifications for matching enabled destinations (never blocks Envelope ACK).
 */
final readonly class NotificationDispatcher
{
    public function __construct(
        private NotificationDestinationRepository $destinationRepository,
        private NotificationPayloadBuilder $payloadBuilder,
        private MessageBusInterface $bus,
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
            $this->bus->dispatch(new DeliverNotificationMessage($destination->getId() ?? 0, $payload));
        }
    }

    public function dispatchTest(Project $project, int $destinationId, string $label): void
    {
        $payload = $this->payloadBuilder->forTest($project, $label);
        $this->bus->dispatch(new DeliverNotificationMessage($destinationId, $payload));
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

        foreach ($this->destinationRepository->findEnabledByProject($project) as $destination) {
            if (!$destination->matchesCategory($level)) {
                continue;
            }
            $id = $destination->getId();
            if (null === $id) {
                continue;
            }
            $this->bus->dispatch(new DeliverNotificationMessage($id, $payload));
        }
    }
}
