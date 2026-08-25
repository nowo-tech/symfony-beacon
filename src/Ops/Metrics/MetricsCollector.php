<?php

declare(strict_types=1);

namespace App\Ops\Metrics;

use App\Notifications\Repository\NotificationDestinationRepository;
use App\Ops\Messenger\MessengerQueueHealth;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Collects Prometheus-oriented series for the /metrics scrape endpoint.
 */
final readonly class MetricsCollector
{
    private const string ACK_KEY = 'beacon.metrics.ingest_ack';

    /** @var list<string> */
    public const array REJECT_REASONS = [
        'unauthorized',
        'forbidden',
        'quota',
        'rate_limit',
        'invalid',
        'other',
    ];

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private MessengerQueueHealth $messengerQueueHealth,
        private NotificationDestinationRepository $destinationRepository,
    ) {
    }

    public function recordIngestAck(): void
    {
        $this->increment(self::ACK_KEY);
    }

    public function recordIngestReject(string $reason): void
    {
        if (!\in_array($reason, self::REJECT_REASONS, true)) {
            $reason = 'other';
        }
        $this->increment('beacon.metrics.ingest_reject.'.$reason);
    }

    /**
     * @return list<array{name: string, type: string, help: string, samples: list<array{labels: array<string, string>, value: float}>}>
     */
    public function collect(): array
    {
        $queue = $this->messengerQueueHealth->asyncPending();
        $pending = $queue['pending'] ?? 0;
        $failedQueue = $queue['failed'] ?? 0;

        $failed = $this->destinationRepository->countWithFailedLastDelivery();

        $rejectSamples = [];
        foreach (self::REJECT_REASONS as $reason) {
            $rejectSamples[] = [
                'labels' => ['reason' => $reason],
                'value' => (float) $this->read('beacon.metrics.ingest_reject.'.$reason),
            ];
        }

        return [
            [
                'name' => 'beacon_messenger_async_pending',
                'type' => 'gauge',
                'help' => 'Pending messages on async_ingest + async Messenger transports',
                'samples' => [['labels' => [], 'value' => (float) $pending]],
            ],
            [
                'name' => 'beacon_messenger_failed_pending',
                'type' => 'gauge',
                'help' => 'Pending messages on the Messenger failure transport',
                'samples' => [['labels' => [], 'value' => (float) $failedQueue]],
            ],
            [
                'name' => 'beacon_notification_destinations_failed',
                'type' => 'gauge',
                'help' => 'Destinations whose last delivery failed',
                'samples' => [['labels' => [], 'value' => (float) $failed]],
            ],
            [
                'name' => 'beacon_ingest_ack_total',
                'type' => 'counter',
                'help' => 'Envelope ingest requests accepted (HTTP 200)',
                'samples' => [['labels' => [], 'value' => (float) $this->read(self::ACK_KEY)]],
            ],
            [
                'name' => 'beacon_ingest_reject_total',
                'type' => 'counter',
                'help' => 'Envelope ingest requests rejected',
                'samples' => $rejectSamples,
            ],
        ];
    }

    private function increment(string $key): void
    {
        $item = $this->cache->getItem($key);
        $value = $item->isHit() ? (int) $item->get() : 0;
        $item->set($value + 1);
        $this->cache->save($item);
    }

    private function read(string $key): int
    {
        $item = $this->cache->getItem($key);

        return $item->isHit() ? (int) $item->get() : 0;
    }
}
