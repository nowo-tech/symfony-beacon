<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Identity\Entity\User;
use App\Notifications\Entity\PushSubscription;
use App\Notifications\Realtime\IssueRealtimeTopics;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Notifications\Service\WebPushClientFactory;
use App\Project\Repository\ProjectRepository;
use App\Shared\Mercure\ConfiguredMercure;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Optional Mercure subscribe tokens and account Web Push subscription management.
 */
#[IsGranted('ROLE_USER')]
final class MemberRealtimeController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ConfiguredMercure $mercure,
        private readonly WebPushClientFactory $webPushFactory,
        private readonly PushSubscriptionRepository $subscriptionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Live update + push bootstrap payload for the logged-in member.
     */
    #[Route('/account/realtime/config', name: 'account_realtime_config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $topics = [];
        $token = null;
        $hubUrl = null;
        if ($this->mercure->isEnabled()) {
            foreach ($this->projectRepository->findAccessibleByUser($user) as $project) {
                $topics[] = IssueRealtimeTopics::forProject($project->getUuid());
            }
            $token = $this->mercure->createSubscriberToken($topics);
            $hubUrl = $this->mercure->getPublicUrl();
        }

        return $this->json([
            'mercure' => [
                'enabled' => $this->mercure->isEnabled(),
                'hubUrl' => $hubUrl,
                'token' => $token,
                'topics' => $topics,
            ],
            'push' => [
                'preferenceEnabled' => $user->isPushNotificationsEnabled(),
                'vapidPublicKey' => $this->webPushFactory->isConfigured()
                    ? $this->webPushFactory->getPublicKey()
                    : null,
                'configured' => $this->webPushFactory->isConfigured(),
            ],
        ]);
    }

    #[Route('/account/push/subscribe', name: 'account_push_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('account_push', $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        if (!$user->isPushNotificationsEnabled()) {
            return $this->json(['ok' => false, 'error' => 'preference_disabled'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->webPushFactory->isConfigured()) {
            return $this->json(['ok' => false, 'error' => 'push_not_configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            /** @var array{endpoint?: mixed, keys?: mixed, contentEncoding?: mixed} $payload */
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->json(['ok' => false, 'error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $endpoint = $payload['endpoint'] ?? null;
        $keys = $payload['keys'] ?? null;
        if (!\is_string($endpoint) || '' === $endpoint || !\is_array($keys)) {
            return $this->json(['ok' => false, 'error' => 'invalid_subscription'], Response::HTTP_BAD_REQUEST);
        }

        $p256dh = $keys['p256dh'] ?? null;
        $auth = $keys['auth'] ?? null;
        if (!\is_string($p256dh) || '' === $p256dh || !\is_string($auth) || '' === $auth) {
            return $this->json(['ok' => false, 'error' => 'invalid_keys'], Response::HTTP_BAD_REQUEST);
        }

        $encoding = $payload['contentEncoding'] ?? 'aes128gcm';
        if (!\is_string($encoding) || '' === $encoding) {
            $encoding = 'aes128gcm';
        }

        $hash = hash('sha256', $endpoint);
        $subscription = $this->subscriptionRepository->findOneByEndpointHash($hash);
        if (!$subscription instanceof PushSubscription) {
            $subscription = new PushSubscription($user);
            $this->entityManager->persist($subscription);
        } elseif ($subscription->getUser()->getId() !== $user->getId()) {
            return $this->json(['ok' => false, 'error' => 'endpoint_owned'], Response::HTTP_CONFLICT);
        }

        $subscription->setSubscription(
            $endpoint,
            $p256dh,
            $auth,
            $encoding,
            $request->headers->get('User-Agent'),
        );

        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/account/push/unsubscribe', name: 'account_push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('account_push', $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        try {
            /** @var array{endpoint?: mixed} $payload */
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->json(['ok' => false, 'error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $endpoint = $payload['endpoint'] ?? null;
        if (\is_string($endpoint) && '' !== $endpoint) {
            $this->subscriptionRepository->deleteByEndpointHash(hash('sha256', $endpoint));
        } else {
            foreach ($this->subscriptionRepository->findByUser($user) as $row) {
                $this->entityManager->remove($row);
            }
            $this->entityManager->flush();
        }

        return $this->json(['ok' => true]);
    }
}
