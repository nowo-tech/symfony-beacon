<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\MessageHandler\ProcessEnvelopeHandler;
use App\Issues\Entity\Issue;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class ProcessEnvelopeGovernanceTest extends DatabaseWebTestCase
{
    public function testWorkerDropsEnvelopeWhenIngestDisabled(): void
    {
        [, , $project] = $this->bootWithDemoProject('worker-suspend@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $project->setIngestEnabled(false);
        $em->flush();

        $handler = self::getContainer()->get(ProcessEnvelopeHandler::class);
        $handler(new ProcessEnvelopeMessage(
            $project->getId() ?? 0,
            $this->eventEnvelope('gov-drop-1', 'Should not persist'),
            new DateTimeImmutable()->format(\DATE_ATOM),
        ));

        self::assertCount(0, $em->getRepository(Issue::class)->findBy(['project' => $project]));
    }

    public function testWorkerDropsEnvelopeWhenDailyQuotaExceeded(): void
    {
        [, , $project] = $this->bootWithDemoProject('worker-quota@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $project->setEventQuotaDaily(1);
        $em->flush();

        $handler = self::getContainer()->get(ProcessEnvelopeHandler::class);
        $handler(new ProcessEnvelopeMessage(
            $project->getId() ?? 0,
            $this->eventEnvelope('gov-quota-1', 'First event under quota'),
            new DateTimeImmutable()->format(\DATE_ATOM),
        ));
        self::assertCount(1, $em->getRepository(Issue::class)->findBy(['project' => $project]));

        $handler(new ProcessEnvelopeMessage(
            $project->getId() ?? 0,
            $this->eventEnvelope('gov-quota-2', 'Second event over quota'),
            new DateTimeImmutable()->format(\DATE_ATOM),
        ));

        $em->clear();
        self::assertCount(1, $em->getRepository(Issue::class)->findBy(['project' => $project]));
    }

    private function eventEnvelope(string $eventId, string $message): string
    {
        $header = json_encode(['dsn' => 'https://x@localhost/1'], \JSON_THROW_ON_ERROR);
        $item = json_encode(['type' => 'event'], \JSON_THROW_ON_ERROR);
        $payload = json_encode([
            'event_id' => $eventId,
            'message' => $message,
            'level' => 'error',
            'platform' => 'php',
            'exception' => [
                'values' => [[
                    'type' => 'RuntimeException',
                    'value' => $message,
                ]],
            ],
        ], \JSON_THROW_ON_ERROR);

        return $header."\n".$item."\n".$payload;
    }
}
