<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class IssueDashboardFunctionalTest extends DatabaseWebTestCase
{
    public function testIssueAppearsAfterIngest(): void
    {
        [$client, $user, $project, $apiKey] = $this->bootWithDemoProject();

        $eventId = bin2hex(random_bytes(16));
        $body = implode("\n", [
            json_encode(['event_id' => $eventId], \JSON_THROW_ON_ERROR),
            json_encode(['type' => 'event'], \JSON_THROW_ON_ERROR),
            json_encode([
                'event_id' => $eventId,
                'message' => 'Dashboard visible error',
                'level' => 'error',
                'exception' => [
                    'values' => [[
                        'type' => 'LogicException',
                        'value' => 'Dashboard visible error',
                        'stacktrace' => ['frames' => [['filename' => 'x.php', 'function' => 'f', 'lineno' => 1]]],
                    ]],
                ],
            ], \JSON_THROW_ON_ERROR),
        ]);

        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getId().'/envelope/',
            [],
            [],
            $this->beaconAuthHeaders($apiKey),
            $body,
        );
        self::assertResponseIsSuccessful();

        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Dashboard visible error');

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/analytics');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Analytics');
    }
}
