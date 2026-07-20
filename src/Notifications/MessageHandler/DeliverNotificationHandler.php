<?php

declare(strict_types=1);

namespace App\Notifications\MessageHandler;

use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Message\DeliverNotificationMessage;
use App\Notifications\Repository\NotificationDestinationRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Delivers one notification attempt to Slack or a generic HTTP webhook.
 */
#[AsMessageHandler]
final readonly class DeliverNotificationHandler
{
    public function __construct(
        private NotificationDestinationRepository $destinationRepository,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeliverNotificationMessage $message): void
    {
        $destination = $this->destinationRepository->find($message->destinationId);
        if (null === $destination || !$destination->isEnabled()) {
            return;
        }

        $url = $destination->getEndpointUrl();
        if ('' === $url) {
            $this->logger->warning('Notification destination has empty URL.', [
                'destination_id' => $message->destinationId,
            ]);

            return;
        }

        try {
            if (NotificationDestinationType::Slack === $destination->getType()) {
                $body = [
                    'text' => (string) ($message->payload['summary'] ?? 'Beacon notification'),
                    'beacon' => $message->payload,
                ];
            } else {
                $body = $message->payload;
            }

            $response = $this->httpClient->request('POST', $url, [
                'json' => $body,
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'symfony-beacon-notifications/1.0',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException(\sprintf('Destination returned HTTP %d', $status));
            }
        } catch (TransportExceptionInterface|Throwable $e) {
            $this->logger->error('Notification delivery failed.', [
                'destination_id' => $message->destinationId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
