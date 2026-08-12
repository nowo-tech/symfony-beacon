<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * NelmioApiDocBundle Swagger UI is linked from Administration (ROLE_ADMIN).
 */
final class ApiDocAccessTest extends DatabaseWebTestCase
{
    public function testApiDocRequiresAuthentication(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/admin/api/doc');
        self::assertResponseRedirects('/en/login');

        $client->request(Request::METHOD_GET, '/admin/api/doc.json');
        self::assertResponseRedirects('/en/login');
    }

    public function testMemberUserCannotOpenApiDoc(): void
    {
        [$client, $user] = $this->bootWithDemoProject('apidoc-member@example.com');
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/admin/api/doc');
        self::assertResponseStatusCodeSame(403);

        $client->request(Request::METHOD_GET, '/admin/api/doc.json');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanOpenSwaggerUiAndJson(): void
    {
        [$client, $user] = $this->bootWithDemoProject('apidoc-admin@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        self::getContainer()->get('doctrine')->getManager()->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/api/doc"]');

        $client->request(Request::METHOD_GET, '/admin/api/doc');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-app-shell]');
        self::assertSelectorExists('#administration-menu-navigation');
        self::assertSelectorExists('.api-docs');
        self::assertSelectorExists('#swagger-ui');
        self::assertSelectorNotExists('a#logo');
        self::assertSelectorTextContains('.api-docs__title', 'API docs');
        $html = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('swagger', strtolower($html));
        // CSP allows only 'self'; assets must not load from jsDelivr CDN.
        self::assertStringNotContainsString('cdn.jsdelivr.net', $html);
        self::assertStringContainsString('/bundles/nelmioapidoc/swagger-ui/swagger-ui.css', $html);
        self::assertStringContainsString('/bundles/nelmioapidoc/swagger-ui/swagger-ui-bundle.js', $html);

        $client->request(Request::METHOD_GET, '/admin/api/doc.json');
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
        self::assertStringContainsString('quota', strtolower((string) ($ingest['responses']['429']['description'] ?? '')));

        self::assertArrayHasKey('/api/{projectId}/otlp/v1/logs', $json['paths']);
        self::assertSame('ingestOtlpLogs', $json['paths']['/api/{projectId}/otlp/v1/logs']['post']['operationId'] ?? null);
        $schemes = $json['components']['securitySchemes'] ?? [];
        self::assertArrayHasKey('BeaconAuth', $schemes);
        self::assertArrayHasKey('BeaconReadToken', $schemes);
        self::assertArrayHasKey('BeaconKeyQuery', $schemes);
        self::assertArrayHasKey('BeaconSecretQuery', $schemes);

        self::assertArrayHasKey('/api/projects/{projectUuid}/issues', $json['paths']);
        self::assertSame('readProjectIssues', $json['paths']['/api/projects/{projectUuid}/issues']['get']['operationId'] ?? null);
        self::assertArrayHasKey('/api/projects/{projectUuid}/issues/{issueUuid}', $json['paths']);

        self::assertArrayHasKey('/health/live', $json['paths']);
        self::assertArrayHasKey('/health/ready', $json['paths']);
        self::assertSame('healthLive', $json['paths']['/health/live']['get']['operationId'] ?? null);
        self::assertSame('healthReady', $json['paths']['/health/ready']['get']['operationId'] ?? null);
    }
}
