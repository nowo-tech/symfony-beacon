<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class OtlpTracesIngestFunctionalTest extends DatabaseWebTestCase
{
    public function testUnauthorizedWithoutKey(): void
    {
        [$client, , $project] = $this->bootWithDemoProject();

        $client->request(Request::METHOD_POST, '/api/'.$project->getId().'/otlp/v1/traces', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        self::assertResponseStatusCodeSame(401);
    }

    public function testRejectsQueryAuth(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject('otlp-traces-query@example.com');

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getId().'/otlp/v1/traces?beacon_key='.$apiKey->getPublicKey().'&beacon_secret='.$apiKey->getSecretKey(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}',
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testIngestsErrorSpanAndCreatesIssue(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject('otlp-traces-ok@example.com');

        $body = json_encode([
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'api']],
                        ['key' => 'deployment.environment', 'value' => ['stringValue' => 'test']],
                    ],
                ],
                'scopeSpans' => [[
                    'spans' => [[
                        'traceId' => 'deadbeef',
                        'spanId' => 'cafebabe',
                        'name' => 'POST /orders',
                        'endTimeUnixNano' => (string) ((int) (microtime(true) * 1_000_000_000)),
                        'status' => ['code' => 2, 'message' => 'OTLP span failed'],
                        'attributes' => [
                            ['key' => 'exception.type', 'value' => ['stringValue' => 'RuntimeException']],
                            ['key' => 'exception.message', 'value' => ['stringValue' => 'OTLP span failed']],
                        ],
                    ]],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getId().'/otlp/v1/traces',
            [],
            [],
            $this->beaconAuthHeaders($apiKey) + ['CONTENT_TYPE' => 'application/json'],
            $body,
        );

        self::assertResponseIsSuccessful();

        $em = self::getContainer()->get('doctrine')->getManager();
        /** @var list<Issue> $issues */
        $issues = $em->getRepository(Issue::class)->findAll();
        self::assertNotEmpty($issues);
        self::assertStringContainsString('OTLP span failed', $issues[0]->getTitle());

        /** @var list<Event> $events */
        $events = $em->getRepository(Event::class)->findAll();
        self::assertNotEmpty($events);
        self::assertSame('otlp', $events[0]->getPlatform());
        self::assertSame('test', $events[0]->getEnvironment());
    }

    public function testOkSpansAckWithoutIssues(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject('otlp-traces-ok-only@example.com');

        $body = json_encode([
            'resourceSpans' => [[
                'scopeSpans' => [[
                    'spans' => [[
                        'name' => 'GET /health',
                        'status' => ['code' => 1],
                    ]],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getId().'/otlp/v1/traces',
            [],
            [],
            $this->beaconAuthHeaders($apiKey) + ['CONTENT_TYPE' => 'application/json'],
            $body,
        );

        self::assertResponseIsSuccessful();
        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(Issue::class)->count([]));
    }
}
