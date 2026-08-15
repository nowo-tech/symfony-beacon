<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\GuestLocaleController;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GuestLocaleControllerTest extends TestCase
{
    public function testLocalizePublicPathForDefaultAndNonDefaultLocale(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod(GuestLocaleController::class, 'localizePublicPath');

        self::assertSame('/login', $method->invoke($controller, '/es/login', 'en'));
        self::assertSame('/es/login', $method->invoke($controller, '/login', 'es'));
        self::assertSame('/de/legal/privacy?x=1', $method->invoke($controller, '/en/legal/privacy?x=1', 'de'));
        self::assertNull($method->invoke($controller, '/projects/1', 'es'));
    }

    public function testSwitchRejectsDisabledLocale(): void
    {
        $controller = $this->controller();
        $this->expectException(NotFoundHttpException::class);
        $controller->switch(Request::create('/locale/xx', Request::METHOD_POST), 'xx');
    }

    private function controller(): GuestLocaleController
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $name, array $params = []): string {
            if ('nowo_auth_kit_login_unlocalized' === $name) {
                return '/login';
            }
            if ('nowo_auth_kit_login' === $name) {
                return '/'.($params['_locale'] ?? 'en').'/login';
            }
            if ('guest_locale_switch' === $name) {
                return '/locale/'.($params['locale'] ?? 'en');
            }

            return '/';
        });
        $container = new Container();
        $container->set('router', $urls);
        $container->set('parameter_bag', new class {
            public function get(string $name): mixed
            {
                return 'kernel.enabled_locales' === $name ? ['en', 'es', 'de'] : null;
            }

            public function has(string $name): bool
            {
                return 'kernel.enabled_locales' === $name;
            }
        });

        $controller = new GuestLocaleController(
            'en',
            new CsrfOnlyFormFactory($this->createStub(FormFactoryInterface::class)),
        );
        $controller->setContainer($container);

        return $controller;
    }
}
