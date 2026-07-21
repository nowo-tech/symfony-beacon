<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Menu\DashboardMenuDemoSeeder;
use Symfony\Component\HttpFoundation\Request;

/**
 * NelmioApiDocBundle Swagger UI is linked from the Dashboard (Panel) sidebar.
 */
final class ApiDocAccessTest extends DatabaseWebTestCase
{
    public function testApiDocRequiresAuthentication(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/api/doc');
        self::assertResponseRedirects('/en/login');

        $client->request(Request::METHOD_GET, '/api/doc.json');
        self::assertResponseRedirects('/en/login');
    }

    public function testAuthenticatedUserCanOpenSwaggerUiAndJson(): void
    {
        [$client, $user] = $this->bootWithDemoProject('apidoc@example.com');
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/api/doc"]');

        $client->request(Request::METHOD_GET, '/api/doc');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-app-shell]');
        self::assertSelectorExists('#dashboard-menu-navigation');
        self::assertSelectorExists('.api-docs');
        self::assertSelectorExists('#swagger-ui');
        self::assertSelectorNotExists('a#logo');
        self::assertSelectorTextContains('.api-docs__title', 'API docs');
        self::assertStringContainsString('swagger', strtolower($client->getResponse()->getContent() ?: ''));

        $client->request(Request::METHOD_GET, '/api/doc.json');
        self::assertResponseIsSuccessful();
        $json = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertIsArray($json);
        self::assertSame('Symfony Beacon API', $json['info']['title'] ?? null);
        self::assertArrayHasKey('paths', $json);

        self::assertArrayHasKey('/api/{projectId}/envelope/', $json['paths']);
        self::assertArrayHasKey('post', $json['paths']['/api/{projectId}/envelope/']);
        $ingest = $json['paths']['/api/{projectId}/envelope/']['post'];
        self::assertSame('ingestEnvelope', $ingest['operationId'] ?? null);
        self::assertContains('Ingest', $ingest['tags'] ?? []);
        self::assertArrayHasKey('200', $ingest['responses'] ?? []);
        self::assertArrayHasKey('401', $ingest['responses'] ?? []);
        self::assertArrayHasKey('403', $ingest['responses'] ?? []);
        self::assertArrayHasKey('429', $ingest['responses'] ?? []);
        self::assertArrayHasKey('Retry-After', $ingest['responses']['429']['headers'] ?? []);

        $schemes = $json['components']['securitySchemes'] ?? [];
        self::assertArrayHasKey('BeaconAuth', $schemes);
        self::assertArrayHasKey('BeaconKeyQuery', $schemes);
        self::assertArrayHasKey('BeaconSecretQuery', $schemes);

        self::assertArrayHasKey('/health/live', $json['paths']);
        self::assertArrayHasKey('/health/ready', $json['paths']);
        self::assertSame('healthLive', $json['paths']['/health/live']['get']['operationId'] ?? null);
        self::assertSame('healthReady', $json['paths']['/health/ready']['get']['operationId'] ?? null);
    }
}
