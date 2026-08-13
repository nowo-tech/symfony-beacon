<?php

declare(strict_types=1);

namespace App\Tests\Unit\Performance\Service;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Performance\Service\NPlusOneDetector;
use App\Performance\Service\PerformanceEnvelopeWriter;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PerformanceEnvelopeWriterTest extends TestCase
{
    public function testWritesTransactionSpansAndIncrementsStats(): void
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $stat = new DailyProjectStat();
        $stats = $this->createStub(DailyProjectStatRepository::class);
        $stats->method('findOrCreate')->willReturn($stat);

        $writer = new PerformanceEnvelopeWriter(new NPlusOneDetector(), $stats, $em);
        $receivedAt = new DateTimeImmutable('2026-08-13T12:00:00+00:00');
        $result = $writer->write(new Project(), [
            'event_id' => 'evt-1',
            'transaction' => 'GET /dashboard',
            'start_timestamp' => 100.0,
            'timestamp' => 100.25,
            'spans' => [
                [
                    'op' => 'db',
                    'description' => 'SELECT 1',
                    'span_id' => 's1',
                    'start_timestamp' => 100.0,
                    'timestamp' => 100.01,
                ],
                'skip',
                [
                    'op' => 'db',
                    'description' => 'SELECT 1',
                    'span_id' => 's2',
                    'start_timestamp' => 100.02,
                    'timestamp' => 100.03,
                ],
            ],
        ], $receivedAt);

        self::assertSame('evt-1', $result->transaction->getEventId());
        self::assertSame('GET /dashboard', $result->transaction->getTransactionName());
        self::assertSame(2, $result->transaction->getSpanCount());
        self::assertSame(250.0, $result->transaction->getDurationMs());
        self::assertSame(1, $stat->getTransactionCount());
        self::assertCount(1, $persisted);
    }
}
