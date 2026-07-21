<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

/**
 * Builds a configured {@see WebPush} client when VAPID keys are present.
 */
final readonly class WebPushClientFactory
{
    public function __construct(
        private string $vapidPublicKey,
        private string $vapidPrivateKey,
        private string $vapidSubject,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->vapidPublicKey && '' !== $this->vapidPrivateKey;
    }

    public function getPublicKey(): string
    {
        return $this->vapidPublicKey;
    }

    public function create(): WebPush
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Web Push VAPID keys are not configured.');
        }

        return new WebPush([
            'VAPID' => [
                'subject' => '' !== $this->vapidSubject ? $this->vapidSubject : 'mailto:ops@localhost',
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ],
        ]);
    }

    /**
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding: string}
     */
    public function subscriptionArray(
        string $endpoint,
        string $p256dh,
        string $authToken,
        string $contentEncoding,
    ): array {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => $p256dh,
                'auth' => $authToken,
            ],
            'contentEncoding' => $contentEncoding,
        ];
    }

    public function createSubscription(
        string $endpoint,
        string $p256dh,
        string $authToken,
        string $contentEncoding,
    ): Subscription {
        return Subscription::create($this->subscriptionArray($endpoint, $p256dh, $authToken, $contentEncoding));
    }
}
