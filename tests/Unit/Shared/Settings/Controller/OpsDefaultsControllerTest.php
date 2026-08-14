<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Controller;

use App\Shared\Settings\Controller\OpsDefaultsController;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\OpsDefaultsSection;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

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

    public function testEditGetRendersGovernanceForm(): void
    {
        $settings = InstanceSettings::defaults();
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $seen = [];
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );

        $controller = new OpsDefaultsController($repo);
        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        self::assertSame('ok', $controller->edit(Request::create('/admin/ops-defaults/governance'), 'governance')->getContent());
        self::assertSame(OpsDefaultsSection::Governance, $seen['settings/ops_defaults.html.twig']['section']);
        self::assertSame($settings, $seen['settings/ops_defaults.html.twig']['settings']);
        self::assertSame(OpsDefaultsSection::cases(), $seen['settings/ops_defaults.html.twig']['sections']);
    }
}
