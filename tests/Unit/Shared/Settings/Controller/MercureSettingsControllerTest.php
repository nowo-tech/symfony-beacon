<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Controller;

use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Mercure\MercureHubUrlGuard;
use App\Shared\Settings\Controller\MercureSettingsController;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class MercureSettingsControllerTest extends TestCase
{
    public function testEditGetRendersSettingsTemplate(): void
    {
        $settings = InstanceSettings::defaults();
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        $mercure = new ConfiguredMercure($repo, '', '', '', new MercureHubUrlGuard());
        $controller = new MercureSettingsController($repo, $mercure);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->willReturnCallback(
            static function (string $template, array $context) use ($settings): string {
                self::assertSame('settings/mercure.html.twig', $template);
                self::assertSame($settings, $context['settings']);
                self::assertFalse($context['mercure_active']);
                self::assertFalse($context['using_database_url']);
                self::assertFalse($context['using_database_secret']);
                self::assertNull($context['resolved_public_url']);

                return 'mercure';
            },
        );

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        $response = $controller->edit(Request::create('/admin/mercure'));
        self::assertSame('mercure', $response->getContent());
    }
}
