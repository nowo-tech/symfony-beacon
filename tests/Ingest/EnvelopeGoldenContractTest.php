<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\MessageHandler\ProcessEnvelopeHandler;
use App\Ingest\Service\EnvelopeAuthParser;
use App\Ingest\Service\EnvelopeParser;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Performance\Entity\PerfTransaction;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Golden Envelope contract: fixtures mirrored from BeaconBundle (Phase 3.6).
 */
final class EnvelopeGoldenContractTest extends DatabaseWebTestCase
{
    private const string FIXTURES_DIR = __DIR__.'/fixtures/envelope';

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function fixtureProvider(): iterable
    {
        yield 'event_happy' => ['event_happy.ndjson'];
        yield 'event_exception' => ['event_exception.ndjson'];
        yield 'transaction_with_spans' => ['transaction_with_spans.ndjson'];
    }

    #[DataProvider('fixtureProvider')]
    public function testParserAcceptsGoldenFixture(string $file): void
    {
        $raw = $this->loadFixture($file);
        $parsed = new EnvelopeParser()->parse($raw);

        self::assertArrayHasKey('event_id', $parsed['header']);
        self::assertArrayHasKey('dsn', $parsed['header']);
        self::assertCount(1, $parsed['items']);
        self::assertContains($parsed['items'][0]['header']['type'], ['event', 'transaction']);
        self::assertIsArray($parsed['items'][0]['payload']);
    }

    public function testAuthHeaderFromBundleIsParsed(): void
    {
        $parsed = new EnvelopeAuthParser()->parseFromRequest(
            'Beacon beacon_key=pubkey, beacon_secret=secret',
            '',
        );

        self::assertSame('pubkey', $parsed['public_key']);
        self::assertSame('secret', $parsed['secret_key']);
    }

    public function testHandlerPersistsGoldenEventHappy(): void
    {
        [, , $project] = $this->bootWithDemoProject('golden-event@example.com');
        $handler = self::getContainer()->get(ProcessEnvelopeHandler::class);
        $raw = $this->loadFixture('event_happy.ndjson');

        $handler(new ProcessEnvelopeMessage(
            $project->getId() ?? 0,
            $raw,
            new DateTimeImmutable()->format(\DATE_ATOM),
        ));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $event = $em->getRepository(Event::class)->findOneBy(['eventId' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']);
        self::assertInstanceOf(Event::class, $event);
        self::assertSame('1.2.3', $event->getReleaseVersion());
        self::assertSame('8.4.1', $event->getPhpVersion());
        self::assertSame('8.1.0', $event->getSymfonyVersion());
        self::assertSame('user-42', $event->getUserIdentifier());
        self::assertCount(1, $em->getRepository(Issue::class)->findBy(['project' => $project]));
    }

    public function testHandlerPersistsGoldenEventException(): void
    {
        [, , $project] = $this->bootWithDemoProject('golden-exc@example.com');
        $handler = self::getContainer()->get(ProcessEnvelopeHandler::class);

        $handler(new ProcessEnvelopeMessage(
            $project->getId() ?? 0,
            $this->loadFixture('event_exception.ndjson'),
            new DateTimeImmutable()->format(\DATE_ATOM),
        ));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $event = $em->getRepository(Event::class)->findOneBy(['eventId' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb']);
        self::assertInstanceOf(Event::class, $event);
        $issue = $em->getRepository(Issue::class)->findOneBy(['project' => $project]);
        self::assertInstanceOf(Issue::class, $issue);
        self::assertStringContainsString('payment declined', $issue->getTitle());
    }

    public function testHandlerPersistsGoldenTransactionWithSpans(): void
    {
        [, , $project] = $this->bootWithDemoProject('golden-tx@example.com');
        $handler = self::getContainer()->get(ProcessEnvelopeHandler::class);

        $handler(new ProcessEnvelopeMessage(
            $project->getId() ?? 0,
            $this->loadFixture('transaction_with_spans.ndjson'),
            new DateTimeImmutable()->format(\DATE_ATOM),
        ));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var list<PerfTransaction> $txs */
        $txs = $em->getRepository(PerfTransaction::class)->findBy(['project' => $project]);
        self::assertCount(1, $txs);
        self::assertSame('GET /checkout', $txs[0]->getTransactionName());
        self::assertSame(3, $txs[0]->getSpanCount());
        self::assertEqualsWithDelta(250.0, $txs[0]->getDurationMs(), 0.01);
        self::assertSame('cccccccccccccccccccccccccccccccc', $txs[0]->getEventId());
    }

    public function testHttpIngestAcceptsGoldenEventHappy(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject('golden-http@example.com');
        $raw = $this->loadFixture('event_happy.ndjson');

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getId().'/envelope/',
            [],
            [],
            $this->beaconAuthHeaders($apiKey),
            $raw,
        );

        self::assertResponseIsSuccessful();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(
            Event::class,
            $em->getRepository(Event::class)->findOneBy(['eventId' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']),
        );
    }

    private function loadFixture(string $file): string
    {
        $path = self::FIXTURES_DIR.'/'.$file;
        $raw = file_get_contents($path);
        self::assertNotFalse($raw, 'Missing golden fixture: '.$path);

        return $raw;
    }
}
