<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AccountLocaleController;
use App\Identity\Entity\User;
use App\Shared\Form\CsrfOnlyFormFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AccountLocaleControllerTest extends TestCase
{
    public function testSwitchRejectsDisabledLocale(): void
    {
        $controller = $this->controller();
        $this->expectException(NotFoundHttpException::class);
        $controller->switch('xx', Request::create('/account/locale/xx', 'POST'));
    }

    public function testStripLocaleQueryRemovesQueryParam(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod(AccountLocaleController::class, 'stripLocaleQuery');

        self::assertSame('/dashboard?keep=1', $method->invoke($controller, '/dashboard?_locale=es&keep=1'));
        self::assertSame('https://beacon.test/projects/1', $method->invoke($controller, 'https://beacon.test/projects/1?_locale=de'));
        self::assertSame('/dashboard', $method->invoke($controller, '/dashboard'));
    }

    private function controller(): AccountLocaleController
    {
        $user = (new User())->setEmail('u@example.com');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $name, array $params = []): string {
            if ('account_locale_switch' === $name) {
                return '/account/locale/'.($params['locale'] ?? 'en');
            }

            return '/dashboard';
        });

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
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

        $controller = new AccountLocaleController(
            $this->createStub(EntityManagerInterface::class),
            new CsrfOnlyFormFactory($this->createStub(FormFactoryInterface::class)),
        );
        $controller->setContainer($container);

        return $controller;
    }
}
