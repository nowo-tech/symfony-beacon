<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Ops\Metrics\MetricsCollector;
use App\Shared\Metrics\MetricsController;
use App\Shared\Metrics\PrometheusTextFormatter;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class MetricsEndpointTest extends DatabaseWebTestCase
{
    public function testAnonymousWithoutTokenIsUnauthorized(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/metrics');
        self::assertResponseStatusCodeSame(401);
    }

    public function testTokenAllowsScrape(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/metrics', server: [
            'HTTP_AUTHORIZATION' => 'Bearer phpunit-metrics-token',
        ]);
        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('beacon_messenger_async_pending', $body);
        self::assertStringContainsString('beacon_ingest_ack_total', $body);
        self::assertStringContainsString('beacon_notification_destinations_failed', $body);
        self::assertStringContainsString('text/plain', (string) $client->getResponse()->headers->get('Content-Type'));
    }

    public function testQueryTokenIsRejected(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/metrics', [
            'token' => 'phpunit-metrics-token',
        ]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testAdminSessionAllowsScrape(): void
    {
        [$client, $user] = $this->bootWithDemoProject('metrics-admin@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        self::getContainer()->get('doctrine')->getManager()->flush();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/metrics');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('beacon_ingest_reject_total', (string) $client->getResponse()->getContent());
    }

    public function testRequireTokenWithEmptyTokenReturnsServiceUnavailable(): void
    {
        self::createClient();
        $container = self::getContainer();
        $settings = InstanceSettings::defaults();
        $settings->setMetricsRequireToken(true);
        $settings->setMetricsToken(null);
        $repository = $this->createStub(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);
        $controller = new MetricsController(
            $container->get(MetricsCollector::class),
            $container->get(PrometheusTextFormatter::class),
            new InstanceOpsDefaults($repository),
        );
        $response = $controller(Request::create('/metrics'));
        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('not configured', (string) $response->getContent());
    }
}
