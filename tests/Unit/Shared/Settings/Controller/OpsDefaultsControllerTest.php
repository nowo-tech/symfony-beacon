<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Controller;

use App\Shared\Settings\Controller\OpsDefaultsController;
use App\Shared\Settings\OpsDefaultsSection;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OpsDefaultsControllerTest extends TestCase
{
    public function testIndexRedirectsToGovernanceSection(): void
    {
        $controller = new OpsDefaultsController($this->createStub(InstanceSettingsRepository::class));
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $name, array $params = []): string {
            self::assertSame('admin_ops_defaults_section', $name);
            self::assertSame(OpsDefaultsSection::Governance->value, $params['section']);

            return '/admin/ops-defaults/governance';
        });
        $container = new Container();
        $container->set('router', $urls);
        $controller->setContainer($container);

        $response = $controller->index();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/ops-defaults/governance', $response->getTargetUrl());
    }

    public function testEditRejectsUnknownSection(): void
    {
        $controller = new OpsDefaultsController($this->createStub(InstanceSettingsRepository::class));
        $controller->setContainer(new Container());
        $this->expectException(NotFoundHttpException::class);
        $controller->edit(Request::create('/admin/ops-defaults/nope'), 'nope');
    }
}
