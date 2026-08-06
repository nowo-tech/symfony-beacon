<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ops;

use App\Notifications\Repository\NotificationDestinationRepository;
use App\Ops\Metrics\MetricsCollector;
use App\Shared\Health\MessengerQueueHealth;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class MetricsCollectorTest extends TestCase
{
    public function testRecordAndCollect(): void
    {
        $cache = new ArrayAdapter();

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(new RuntimeException('offline'));
        $queue = new MessengerQueueHealth($em);

        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('countWithFailedLastDelivery')->willReturn(2);

        $collector = new MetricsCollector($cache, $queue, $destinations);
        $collector->recordIngestAck();
        $collector->recordIngestAck();
        $collector->recordIngestReject('unauthorized');
        $collector->recordIngestReject('not-a-real-reason');

        $families = $collector->collect();
        $byName = [];
        foreach ($families as $family) {
            $byName[$family['name']] = $family;
        }

        self::assertSame(0.0, $byName['beacon_messenger_async_pending']['samples'][0]['value']);
        self::assertSame(2.0, $byName['beacon_notification_destinations_failed']['samples'][0]['value']);
        self::assertSame(2.0, $byName['beacon_ingest_ack_total']['samples'][0]['value']);

        $rejects = [];
        foreach ($byName['beacon_ingest_reject_total']['samples'] as $sample) {
            $rejects[$sample['labels']['reason']] = $sample['value'];
        }
        self::assertSame(1.0, $rejects['unauthorized']);
        self::assertSame(1.0, $rejects['other']);
        self::assertSame(0.0, $rejects['quota']);
    }
}
