<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ingest;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class OtlpLogsIngestFunctionalTest extends DatabaseWebTestCase
{
    public function testUnauthorizedWithoutKey(): void
    {
        [$client, , $project] = $this->bootWithDemoProject();

        $client->request(Request::METHOD_POST, '/api/'.$project->getUuid().'/otlp/v1/logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        self::assertResponseStatusCodeSame(401);
    }

    public function testRejectsQueryAuth(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject('otlp-query@example.com');

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getUuid().'/otlp/v1/logs?beacon_key='.$apiKey->getPublicKey().'&beacon_secret='.$apiKey->getSecretKey(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}',
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testIngestsErrorLogAndCreatesIssue(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject('otlp-ok@example.com');

        $body = json_encode([
            'resourceLogs' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'api']],
                        ['key' => 'deployment.environment', 'value' => ['stringValue' => 'test']],
                    ],
                ],
                'scopeLogs' => [[
                    'logRecords' => [[
                        'timeUnixNano' => (string) ((int) (microtime(true) * 1_000_000_000)),
                        'severityNumber' => 17,
                        'severityText' => 'ERROR',
                        'body' => ['stringValue' => 'OTLP something broke'],
                        'attributes' => [
                            ['key' => 'exception.type', 'value' => ['stringValue' => 'RuntimeException']],
                            ['key' => 'exception.message', 'value' => ['stringValue' => 'OTLP something broke']],
                        ],
                    ]],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getUuid().'/otlp/v1/logs',
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
        self::assertStringContainsString('OTLP something broke', $issues[0]->getTitle());

        /** @var list<Event> $events */
        $events = $em->getRepository(Event::class)->findAll();
        self::assertNotEmpty($events);
        self::assertSame('otlp', $events[0]->getPlatform());
        self::assertSame('test', $events[0]->getEnvironment());
    }

    public function testOversizedBodyRejected(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject('otlp-big@example.com');
        $huge = '{"resourceLogs":[{"scopeLogs":[{"logRecords":[{"severityNumber":17,"body":{"stringValue":"'.str_repeat('x', 2_100_000).'"}}]}]}]}';

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getUuid().'/otlp/v1/logs',
            [],
            [],
            $this->beaconAuthHeaders($apiKey) + ['CONTENT_TYPE' => 'application/json'],
            $huge,
        );

        self::assertResponseStatusCodeSame(413);
    }
}
