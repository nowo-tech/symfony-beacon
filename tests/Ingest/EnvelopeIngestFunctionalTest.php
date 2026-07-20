<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Performance\Entity\PerfTransaction;
use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class EnvelopeIngestFunctionalTest extends DatabaseWebTestCase
{
    public function testUnauthorizedWithoutKey(): void
    {
        [$client, , $project] = $this->bootWithDemoProject();

        $client->request(Request::METHOD_POST, '/api/'.$project->getId().'/envelope/', [], [], [], '{}');

        self::assertResponseStatusCodeSame(401);
    }

    public function testIngestsEventAndCreatesIssue(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject();

        $eventId = bin2hex(random_bytes(16));
        $body = implode("\n", [
            json_encode(['event_id' => $eventId], \JSON_THROW_ON_ERROR),
            json_encode(['type' => 'event'], \JSON_THROW_ON_ERROR),
            json_encode([
                'event_id' => $eventId,
                'message' => 'Something broke',
                'level' => 'error',
                'platform' => 'php',
                'environment' => 'test',
                'release' => '1.2.3',
                'timestamp' => 1721491200.654321,
                'datetime' => '2024-07-20T16:00:00.654321Z',
                'user' => ['id' => 'user-42', 'email' => 'dev@example.com'],
                'contexts' => [
                    'runtime' => ['name' => 'php', 'version' => '8.4.1'],
                    'framework' => ['name' => 'symfony', 'version' => '8.1.0'],
                ],
                'exception' => [
                    'values' => [[
                        'type' => 'RuntimeException',
                        'value' => 'Something broke',
                        'stacktrace' => [
                            'frames' => [
                                ['filename' => 'src/App.php', 'function' => 'run', 'lineno' => 42],
                            ],
                        ],
                    ]],
                ],
            ], \JSON_THROW_ON_ERROR),
        ]);

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getId().'/envelope/',
            [],
            [],
            ['HTTP_X_SENTRY_AUTH' => 'Sentry sentry_version=7, sentry_key='.$apiKey->getPublicKey()],
            $body,
        );

        self::assertResponseIsSuccessful();

        $em = self::getContainer()->get('doctrine')->getManager();
        /** @var list<Issue> $issues */
        $issues = $em->getRepository(Issue::class)->findAll();
        self::assertCount(1, $issues);
        self::assertStringContainsString('Something broke', $issues[0]->getTitle());
        self::assertSame(1, $issues[0]->getEventCount());

        /** @var Event|null $stored */
        $stored = $em->getRepository(Event::class)->findOneBy(['eventId' => $eventId]);
        self::assertInstanceOf(Event::class, $stored);
        self::assertSame('1.2.3', $stored->getReleaseVersion());
        self::assertSame('8.4.1', $stored->getPhpVersion());
        self::assertSame('8.1.0', $stored->getSymfonyVersion());
        self::assertSame('user-42', $stored->getUserIdentifier());
        self::assertSame('2024-07-20 16:00:00.654321', $stored->getEventTimestamp()->format('Y-m-d H:i:s.u'));
    }

    public function testGroupsSimilarEventsAndCountsOccurrences(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject();
        $headers = ['HTTP_X_SENTRY_AUTH' => 'Sentry sentry_version=7, sentry_key='.$apiKey->getPublicKey()];

        foreach ([10, 99] as $i => $userId) {
            $eventId = bin2hex(random_bytes(16));
            $body = implode("\n", [
                json_encode(['event_id' => $eventId], \JSON_THROW_ON_ERROR),
                json_encode(['type' => 'event'], \JSON_THROW_ON_ERROR),
                json_encode([
                    'event_id' => $eventId,
                    'message' => 'User '.$userId.' not found',
                    'level' => 'error',
                    'platform' => 'php',
                    'environment' => 'test',
                    'exception' => [
                        'values' => [[
                            'type' => 'RuntimeException',
                            'value' => 'User '.$userId.' not found',
                            'stacktrace' => [
                                'frames' => [
                                    [
                                        'filename' => '/app/src/User/Finder.php',
                                        'function' => 'App\\User\\Finder::find',
                                        'lineno' => 20 + $i,
                                        'in_app' => true,
                                    ],
                                ],
                            ],
                        ]],
                    ],
                ], \JSON_THROW_ON_ERROR),
            ]);

            $client->request(Request::METHOD_POST, '/api/'.$project->getId().'/envelope/', [], [], $headers, $body);
            self::assertResponseIsSuccessful();
        }

        $em = self::getContainer()->get('doctrine')->getManager();
        /** @var list<Issue> $issues */
        $issues = $em->getRepository(Issue::class)->findBy(['project' => $project]);
        self::assertCount(1, $issues);
        self::assertSame(2, $issues[0]->getEventCount());
        self::assertNotNull($issues[0]->getFirstSeen());
        self::assertNotNull($issues[0]->getLastSeen());

        $stats = self::getContainer()->get(\App\Issues\Repository\EventRepository::class)
            ->occurrenceStatsForIssue($issues[0]);
        self::assertSame(2, $stats->total);
        self::assertSame(2, $stats->last24h);
        self::assertSame(2, $stats->last7d);
        self::assertSame(2, $stats->last30d);
    }

    public function testIngestsTransactionWithNPlusOne(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject();

        $spans = [];
        for ($i = 1; $i <= 6; ++$i) {
            $spans[] = [
                'span_id' => 'span'.$i,
                'op' => 'db.sql.query',
                'description' => 'SELECT * FROM item WHERE id = '.$i,
                'start_timestamp' => 1.0,
                'timestamp' => 1.01,
            ];
        }

        $eventId = bin2hex(random_bytes(16));
        $body = implode("\n", [
            json_encode(['event_id' => $eventId], \JSON_THROW_ON_ERROR),
            json_encode(['type' => 'transaction'], \JSON_THROW_ON_ERROR),
            json_encode([
                'event_id' => $eventId,
                'transaction' => 'GET /products',
                'start_timestamp' => 1.0,
                'timestamp' => 1.2,
                'spans' => $spans,
            ], \JSON_THROW_ON_ERROR),
        ]);

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getId().'/envelope/',
            [],
            [],
            ['HTTP_X_SENTRY_AUTH' => 'Sentry sentry_version=7, sentry_key='.$apiKey->getPublicKey()],
            $body,
        );

        self::assertResponseIsSuccessful();

        $em = self::getContainer()->get('doctrine')->getManager();
        /** @var list<PerfTransaction> $txs */
        $txs = $em->getRepository(PerfTransaction::class)->findAll();
        self::assertCount(1, $txs);
        self::assertSame(1, $txs[0]->getNPlusOneCount());
        self::assertSame(6, $txs[0]->getSpanCount());
    }
}
