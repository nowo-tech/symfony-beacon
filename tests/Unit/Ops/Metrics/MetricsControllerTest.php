<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ops\Metrics;

use App\Notifications\Repository\NotificationDestinationRepository;
use App\Ops\Metrics\MetricsCollector;
use App\Ops\Metrics\MetricsController;
use App\Ops\Metrics\PrometheusTextFormatter;
use App\Shared\Health\MessengerQueueHealth;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MetricsControllerTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testReturnsServiceUnavailableWhenTokenRequiredButMissing(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setMetricsRequireToken(true);
            $settings->setMetricsToken(null);
        });
        $controller = $this->controller($ops);
        $response = $controller(Request::create('/metrics'));

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('not configured', (string) $response->getContent());
    }

    public function testAcceptsBearerTokenAndRejectsMissingAuth(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setMetricsRequireToken(true);
            $settings->setMetricsToken('metrics-secret');
        });
        $controller = $this->controller($ops, admin: false);

        $unauthorized = $controller(Request::create('/metrics'));
        self::assertSame(401, $unauthorized->getStatusCode());

        $ok = $controller(Request::create('/metrics', server: [
            'HTTP_AUTHORIZATION' => 'Bearer metrics-secret',
        ]));
        self::assertSame(200, $ok->getStatusCode());
        self::assertStringContainsString('text/plain', (string) $ok->headers->get('Content-Type'));
        self::assertNotSame('', (string) $ok->getContent());
    }

    public function testAdminBypassesToken(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setMetricsRequireToken(true);
            $settings->setMetricsToken('metrics-secret');
        });
        $controller = $this->controller($ops, admin: true);
        $response = $controller(Request::create('/metrics'));
        self::assertSame(200, $response->getStatusCode());
    }

    private function controller(object $ops, bool $admin = false): MetricsController
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(new RuntimeException('offline'));
        $collector = new MetricsCollector(
            new ArrayAdapter(),
            new MessengerQueueHealth($em),
            $this->createStub(NotificationDestinationRepository::class),
        );
        $controller = new MetricsController($collector, new PrometheusTextFormatter(), $ops);

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn($admin);
        $container = new Container();
        $container->set('security.authorization_checker', $auth);
        $controller->setContainer($container);

        return $controller;
    }
}
