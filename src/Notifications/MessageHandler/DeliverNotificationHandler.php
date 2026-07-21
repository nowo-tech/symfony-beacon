<?php

declare(strict_types=1);

namespace App\Notifications\MessageHandler;

use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Message\DeliverNotificationMessage;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Service\NotificationDeliveryHistoryRecorder;
use App\Notifications\Service\NotificationOutboundFormatter;
use App\Notifications\Service\OutboundUrlGuard;
use App\Shared\Mailer\ConfiguredMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Delivers one notification attempt (Slack, Discord, Teams, Telegram, email, or HTTP).
 */
#[AsMessageHandler]
final readonly class DeliverNotificationHandler
{
    public function __construct(
        private NotificationDestinationRepository $destinationRepository,
        private NotificationDeliveryHistoryRecorder $deliveryHistoryRecorder,
        private NotificationOutboundFormatter $outboundFormatter,
        private OutboundUrlGuard $outboundUrlGuard,
        private HttpClientInterface $httpClient,
        private ConfiguredMailer $mailer,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(DeliverNotificationMessage $message): void
    {
        $destination = $this->destinationRepository->find($message->destinationId);
        if (null === $destination || !$destination->isEnabled()) {
            return;
        }

        $endpoint = $destination->getEndpointUrl();
        if ('' === $endpoint) {
            $this->logger->warning('Notification destination has empty endpoint.', [
                'destination_id' => $message->destinationId,
            ]);
            $this->deliveryHistoryRecorder->recordFailure($destination, 'Empty endpoint');
            $this->entityManager->flush();

            return;
        }

        try {
            if (NotificationDestinationType::Email === $destination->getType()) {
                $this->deliverEmail($endpoint, $message->payload);
            } else {
                $request = $this->outboundFormatter->httpRequest(
                    $destination->getType(),
                    $endpoint,
                    $message->payload,
                );

                if (NotificationDestinationType::Telegram !== $destination->getType()) {
                    $this->outboundUrlGuard->assertSafeHttpUrl($request['url']);
                }

                $response = $this->httpClient->request('POST', $request['url'], [
                    'json' => $request['json'],
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
            }

            $this->deliveryHistoryRecorder->recordSuccess($destination);
            $this->entityManager->flush();
        } catch (Throwable $e) {
            $this->deliveryHistoryRecorder->recordFailure($destination, $e->getMessage());
            $this->entityManager->flush();

            $this->logger->error('Notification delivery failed.', [
                'destination_id' => $message->destinationId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function deliverEmail(string $to, array $payload): void
    {
        $summary = (string) ($payload['summary'] ?? 'Beacon notification');
        $url = isset($payload['url']) ? (string) $payload['url'] : '';
        $body = $summary;
        if ('' !== $url) {
            $body .= "\n\n".$url;
        }

        $email = new Email()
            ->from($this->mailer->getFromAddress())
            ->to($to)
            ->subject(mb_substr(str_replace("\n", ' ', $summary), 0, 200))
            ->text($body);

        $this->mailer->send($email);
    }
}
