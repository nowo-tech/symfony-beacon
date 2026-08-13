<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ingest;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class OtlpMetricsIngestFunctionalTest extends DatabaseWebTestCase
{
    public function testUnauthorizedWithoutKey(): void
    {
        [$client, , $project] = $this->bootWithDemoProject();

        $client->request(Request::METHOD_POST, '/api/'.$project->getUuid().'/otlp/v1/metrics', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        self::assertResponseStatusCodeSame(401);
    }

    public function testRejectsQueryAuth(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject('otlp-metrics-query@example.com');

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getUuid().'/otlp/v1/metrics?beacon_key='.$apiKey->getPublicKey().'&beacon_secret='.$apiKey->getSecretKey(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}',
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testIngestsErrorMetricAndCreatesIssue(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject('otlp-metrics-ok@example.com');

        $body = json_encode([
            'resourceMetrics' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'api']],
                        ['key' => 'deployment.environment', 'value' => ['stringValue' => 'test']],
                    ],
                ],
                'scopeMetrics' => [[
                    'metrics' => [[
                        'name' => 'http.server.errors',
                        'sum' => [
                            'dataPoints' => [[
                                'asInt' => '1',
                                'timeUnixNano' => (string) ((int) (microtime(true) * 1_000_000_000)),
                                'attributes' => [
                                    ['key' => 'error.type', 'value' => ['stringValue' => 'RuntimeException']],
                                    ['key' => 'exception.message', 'value' => ['stringValue' => 'OTLP metric failed']],
                                ],
                            ]],
                        ],
                    ]],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getUuid().'/otlp/v1/metrics',
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
        self::assertStringContainsString('OTLP metric failed', $issues[0]->getTitle());

        /** @var list<Event> $events */
        $events = $em->getRepository(Event::class)->findAll();
        self::assertNotEmpty($events);
        self::assertSame('otlp', $events[0]->getPlatform());
        self::assertSame('test', $events[0]->getEnvironment());
    }

    public function testHealthyMetricsAckWithoutIssues(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject('otlp-metrics-ok-only@example.com');

        $body = json_encode([
            'resourceMetrics' => [[
                'scopeMetrics' => [[
                    'metrics' => [[
                        'name' => 'http.server.request.duration',
                        'histogram' => [
                            'dataPoints' => [[
                                'count' => '5',
                                'sum' => 0.2,
                            ]],
                        ],
                    ]],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getUuid().'/otlp/v1/metrics',
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
