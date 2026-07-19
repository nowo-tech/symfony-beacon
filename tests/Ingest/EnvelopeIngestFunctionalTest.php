<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Issues\Entity\Issue;
use App\Performance\Entity\PerfTransaction;
use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class EnvelopeIngestFunctionalTest extends DatabaseWebTestCase
{
    public function testUnauthorizedWithoutKey(): void
    {
        [$client, , $project] = $this->bootWithDemoProject();

        $client->request(Request::METHOD_POST, '/api/'.$project->getId().'/envelope/', [], [], [], "{}");

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

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var list<Issue> $issues */
        $issues = $em->getRepository(Issue::class)->findAll();
        self::assertCount(1, $issues);
        self::assertStringContainsString('Something broke', $issues[0]->getTitle());
        self::assertSame(1, $issues[0]->getEventCount());
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

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var list<PerfTransaction> $txs */
        $txs = $em->getRepository(PerfTransaction::class)->findAll();
        self::assertCount(1, $txs);
        self::assertSame(1, $txs[0]->getNPlusOneCount());
        self::assertSame(6, $txs[0]->getSpanCount());
    }
}
