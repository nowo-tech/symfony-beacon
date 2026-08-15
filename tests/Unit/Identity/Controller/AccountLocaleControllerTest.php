<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AccountLocaleController;
use App\Identity\Entity\User;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
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
        $controller->switch('xx', Request::create('/account/locale/xx', Request::METHOD_POST));
    }

    public function testSwitchPersistsLocaleAndRedirects(): void
    {
        $user = new User()->setEmail('u@example.com');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['redirect' => '/projects/1?_locale=en&keep=1']);

        $inner = $this->createStub(FormFactoryInterface::class);
        $inner->method('createNamed')->willReturn($form);
        $inner->method('create')->willReturn($form);

        $controller = new AccountLocaleController($em, new CsrfOnlyFormFactory($inner));
        $session = $this->boot($controller, $user, flash: true);

        $request = Request::create('https://beacon.test/account/locale/es', Request::METHOD_POST);
        $request->setSession($session);

        $response = $controller->switch('es', $request);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('es', $user->getPreferredLocale());
        self::assertSame('es', $session->get('_locale'));
        self::assertSame(['flash.preferences.locale_saved'], $session->getFlashBag()->peek('success'));
        self::assertSame('/projects/1?keep=1', $response->getTargetUrl());
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
        $user = new User()->setEmail('u@example.com');
        $controller = new AccountLocaleController(
            $this->createStub(EntityManagerInterface::class),
            new CsrfOnlyFormFactory($this->createStub(FormFactoryInterface::class)),
        );
        $this->boot($controller, $user);

        return $controller;
    }

    private function boot(object $controller, User $user, bool $flash = false): Session
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $name, array $params = []): string {
            if ('account_locale_switch' === $name) {
                return '/account/locale/'.($params['locale'] ?? 'en');
            }

            return '/dashboard';
        });

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('https://beacon.test/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

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
        if ($flash) {
            $container->set('request_stack', $stack);
        }
        $controller->setContainer($container);

        return $session;
    }
}
