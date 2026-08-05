<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Analytics\Entity\DailyProjectStat;
use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\MessageHandler\ProcessEnvelopeHandler;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class ProcessEnvelopeDailyStatTest extends DatabaseWebTestCase
{
    public function testMultiItemFirstDayEnvelopeCreatesSingleDailyStat(): void
    {
        [, , $project] = $this->bootWithDemoProject('daily-stat-multi@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $handler = self::getContainer()->get(ProcessEnvelopeHandler::class);

        $receivedAt = new DateTimeImmutable('2026-08-05T10:00:00+00:00');
        $handler(new ProcessEnvelopeMessage(
            $project->getId() ?? 0,
            $this->multiEventEnvelope([
                ['id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'message' => 'First of day'],
                ['id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'message' => 'Second of day'],
            ]),
            $receivedAt->format(\DATE_ATOM),
        ));

        /** @var list<DailyProjectStat> $stats */
        $stats = $em->getRepository(DailyProjectStat::class)->findBy(['project' => $project]);
        self::assertCount(1, $stats);
        self::assertSame(2, $stats[0]->getErrorCount());
    }

    /**
     * @param list<array{id: string, message: string}> $events
     */
    private function multiEventEnvelope(array $events): string
    {
        $parts = [json_encode(['dsn' => 'https://x@localhost/1'], \JSON_THROW_ON_ERROR)];
        foreach ($events as $event) {
            $parts[] = json_encode(['type' => 'event'], \JSON_THROW_ON_ERROR);
            $parts[] = json_encode([
                'event_id' => $event['id'],
                'message' => $event['message'],
                'level' => 'error',
                'platform' => 'php',
                'fingerprint' => ['manual', $event['id']],
                'exception' => [
                    'values' => [[
                        'type' => 'RuntimeException',
                        'value' => $event['message'],
                    ]],
                ],
            ], \JSON_THROW_ON_ERROR);
        }

        return implode("\n", $parts);
    }
}
