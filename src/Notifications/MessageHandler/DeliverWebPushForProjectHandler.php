<?php

declare(strict_types=1);

namespace App\Notifications\MessageHandler;

use App\Notifications\Entity\PushSubscription;
use App\Notifications\Message\DeliverWebPushForProjectMessage;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Notifications\Service\WebPushClientFactory;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Sends Web Push notifications to opted-in members of a project.
 */
#[AsMessageHandler]
final readonly class DeliverWebPushForProjectHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectMembershipRepository $membershipRepository,
        private PushSubscriptionRepository $subscriptionRepository,
        private WebPushClientFactory $webPushFactory,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeliverWebPushForProjectMessage $message): void
    {
        if (!$this->webPushFactory->isConfigured()) {
            return;
        }

        $project = $this->projectRepository->find($message->projectId);
        if (null === $project) {
            return;
        }

        $users = $this->membershipRepository->findUsersByProject($project);
        $subscriptions = $this->subscriptionRepository->findForPushEnabledUsers($users);
        if ([] === $subscriptions) {
            return;
        }

        $payloadJson = json_encode($message->payload, \JSON_THROW_ON_ERROR);
        $webPush = $this->webPushFactory->create();
        $stale = [];

        foreach ($subscriptions as $subscription) {
            try {
                $report = $webPush->sendOneNotification(
                    $this->webPushFactory->createSubscription(
                        $subscription->getEndpoint(),
                        $subscription->getP256dh(),
                        $subscription->getAuthToken(),
                        $subscription->getContentEncoding(),
                    ),
                    $payloadJson,
                    ['TTL' => 3600, 'urgency' => 'high'],
                );
                if ($report->isSubscriptionExpired()) {
                    $stale[] = $subscription;
                }
            } catch (Throwable $e) {
                $this->logger->warning('Web Push delivery failed.', [
                    'subscription' => $subscription->getId(),
                    'exception' => $e,
                ]);
            }
        }

        foreach ($stale as $gone) {
            if ($gone instanceof PushSubscription) {
                $this->entityManager->remove($gone);
            }
        }
        if ([] !== $stale) {
            $this->entityManager->flush();
        }
    }
}
