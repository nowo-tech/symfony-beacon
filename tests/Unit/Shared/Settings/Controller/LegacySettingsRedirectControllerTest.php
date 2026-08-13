<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Controller;

use App\Shared\Settings\Controller\LegacySettingsRedirectController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LegacySettingsRedirectControllerTest extends TestCase
{
    public function testPermanentRedirectsToAdminRoutes(): void
    {
        $generated = [];
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $name, array $params = []) use (&$generated): string {
            $generated[] = [$name, $params];

            return '/admin/'.$name;
        });
        $container = new Container();
        $container->set('router', $urls);
        $controller = new LegacySettingsRedirectController();
        $controller->setContainer($container);

        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $controller->appearance()->getStatusCode());
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $controller->appearanceSection('colors', 'accents')->getStatusCode());
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $controller->mailer()->getStatusCode());
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $controller->mailerTest()->getStatusCode());
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $controller->mercure()->getStatusCode());
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $controller->opsDefaults()->getStatusCode());
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $controller->opsDefaultsSection('metrics')->getStatusCode());
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $controller->instanceConfig()->getStatusCode());
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $controller->instanceConfigExport()->getStatusCode());
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $controller->instanceConfigImport()->getStatusCode());

        self::assertContains(['admin_appearance', []], $generated);
        self::assertContains(['admin_appearance_section', ['section' => 'colors', 'sub' => 'accents']], $generated);
        self::assertContains(['admin_ops_defaults_section', ['section' => 'metrics']], $generated);
        self::assertContains(['admin_instance_config', []], $generated);
    }
}
