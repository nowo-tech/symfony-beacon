<?php

declare(strict_types=1);

namespace App\Notifications\Realtime;

use App\Issues\Entity\Issue;
use App\Notifications\Message\DeliverWebPushForProjectMessage;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\WebPushClientFactory;
use App\Project\Entity\Project;
use App\Shared\Mercure\ConfiguredMercure;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Publishes optional Mercure updates and queues optional Web Push for new issues.
 */
final readonly class MemberIssueRealtimeNotifier implements MemberIssueRealtimeNotifierInterface
{
    public function __construct(
        private ConfiguredMercure $mercure,
        private WebPushClientFactory $webPushFactory,
        private NotificationPayloadBuilder $payloadBuilder,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function notifyNewIssue(Project $project, Issue $issue): void
    {
        $payload = $this->payloadBuilder->forNewIssue($project, $issue);
        $topic = IssueRealtimeTopics::forProject($project->getUuid());

        if ($this->mercure->isEnabled()) {
            try {
                $this->mercure->publish(new Update(
                    $topic,
                    json_encode($payload, \JSON_THROW_ON_ERROR),
                    true,
                ));
            } catch (Throwable $e) {
                $this->logger->warning('Mercure publish failed for new issue.', [
                    'project' => $project->getUuid(),
                    'issue' => $issue->getUuid(),
                    'exception' => $e,
                ]);
            }
        }

        if (!$this->webPushFactory->isConfigured()) {
            return;
        }

        $projectId = $project->getId();
        if (null === $projectId) {
            return;
        }

        $this->bus->dispatch(new DeliverWebPushForProjectMessage($projectId, $payload));
    }
}
