<?php

declare(strict_types=1);

namespace App\Notifications\MessageHandler;

use App\Identity\Entity\User;
use App\Notifications\Entity\PushSubscription;
use App\Notifications\Message\DeliverWebPushForProjectMessage;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Notifications\Service\WebPushClientFactory;
use App\Notifications\Service\WebPushEndpointGuard;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Sends Web Push notifications to opted-in members of a project.
 */
#[AsMessageHandler(sign: true)]
final readonly class DeliverWebPushForProjectHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectMembershipRepository $membershipRepository,
        private PushSubscriptionRepository $subscriptionRepository,
        private WebPushClientFactory $webPushFactory,
        private WebPushEndpointGuard $webPushEndpointGuard,
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
        if (null !== $message->eligibleUserIds) {
            if ([] === $message->eligibleUserIds) {
                return;
            }
            $allowed = array_fill_keys($message->eligibleUserIds, true);
            $users = array_values(array_filter(
                $users,
                static fn (User $user): bool => null !== $user->getId() && isset($allowed[$user->getId()]),
            ));
        }
        $subscriptions = $this->subscriptionRepository->findForPushEnabledUsers($users);
        if ([] === $subscriptions) {
            return;
        }

        $payloadJson = json_encode($message->payload, \JSON_THROW_ON_ERROR);
        $webPush = $this->webPushFactory->create();
        $stale = [];

        foreach ($subscriptions as $subscription) {
            try {
                $this->webPushEndpointGuard->assertSafeEndpoint($subscription->getEndpoint());
            } catch (InvalidArgumentException $e) {
                $this->logger->warning('Removing unsafe Web Push endpoint.', [
                    'subscription' => $subscription->getId(),
                    'exception' => $e->getMessage(),
                ]);
                $stale[] = $subscription;

                continue;
            }

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
