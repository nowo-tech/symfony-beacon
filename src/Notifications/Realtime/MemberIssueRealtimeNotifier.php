<?php

declare(strict_types=1);

namespace App\Notifications\Realtime;

use App\Issues\Entity\Issue;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Message\DeliverWebPushForProjectMessage;
use App\Notifications\Service\MemberAlertPreferenceEvaluator;
use App\Notifications\Service\WebPushClientFactory;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Shared\Mercure\ConfiguredMercure;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Publishes optional Mercure updates and queues optional Web Push for member alerts.
 */
final readonly class MemberIssueRealtimeNotifier implements MemberIssueRealtimeNotifierInterface
{
    public function __construct(
        private ConfiguredMercure $mercure,
        private WebPushClientFactory $webPushFactory,
        private MemberAlertPreferenceEvaluator $preferenceEvaluator,
        private ProjectMembershipRepository $membershipRepository,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function notify(MemberAlertEvent $event, Project $project, Issue $issue, array $payload): void
    {
        $payload['event'] = $event->value;
        $users = $this->preferenceEvaluator->filterEligibleUsers(
            $this->membershipRepository->findUsersByProject($project),
            $project,
            $issue,
            $event,
        );
        $eligibleIds = [];
        $mercureTopics = [];

        foreach ($users as $user) {
            $id = $user->getId();
            if (null !== $id) {
                $eligibleIds[] = $id;
            }
            if ($this->mercure->isEnabled()) {
                $mercureTopics[] = IssueRealtimeTopics::forUser($user->getUuid());
            }
        }

        if ($this->mercure->isEnabled() && [] !== $mercureTopics) {
            try {
                $this->mercure->publish(new Update(
                    $mercureTopics,
                    json_encode($payload, \JSON_THROW_ON_ERROR),
                    true,
                ));
            } catch (Throwable $e) {
                $this->logger->warning('Mercure publish failed for member alert.', [
                    'event' => $event->value,
                    'project' => $project->getUuid(),
                    'issue' => $issue->getUuid(),
                    'exception' => $e,
                ]);
            }
        }

        if (!$this->webPushFactory->isConfigured() || [] === $eligibleIds) {
            return;
        }

        $projectId = $project->getId();
        if (null === $projectId) {
            return;
        }

        $this->bus->dispatch(new DeliverWebPushForProjectMessage($projectId, $payload, $eligibleIds));
    }
}
