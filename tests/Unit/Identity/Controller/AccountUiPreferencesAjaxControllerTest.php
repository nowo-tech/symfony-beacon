<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AccountUiPreferencesAjaxController;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AccountUiPreferencesAjaxControllerTest extends TestCase
{
    public function testProductTourSeenAndThemeAndWidth(): void
    {
        $user = (new User())->setEmail('u@example.com');
        $flush = 0;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willReturnCallback(static function () use (&$flush): void {
            ++$flush;
        });
        $controller = $this->controller($user, $em, csrfValid: true);

        $badCsrf = $this->controller($user, $em, csrfValid: false);
        self::assertSame(Response::HTTP_FORBIDDEN, $badCsrf->theme(Request::create('/account/theme', 'POST', content: '{"theme":"dark"}'))->getStatusCode());

        $invalidJson = $controller->theme(Request::create(
            '/account/theme',
            'POST',
            server: ['HTTP_X_CSRF_TOKEN' => 'ok'],
            content: '{',
        ));
        self::assertSame(Response::HTTP_BAD_REQUEST, $invalidJson->getStatusCode());

        $theme = $controller->theme(Request::create(
            '/account/theme',
            'POST',
            server: ['HTTP_X_CSRF_TOKEN' => 'ok'],
            content: '{"theme":"dark"}',
        ));
        self::assertSame(Response::HTTP_OK, $theme->getStatusCode());
        self::assertSame('dark', $user->getPreferredTheme());

        $width = $controller->contentWidth(Request::create(
            '/account/content-width',
            'POST',
            server: ['HTTP_X_CSRF_TOKEN' => 'ok'],
            content: '{"contentWidth":"full"}',
        ));
        self::assertSame(Response::HTTP_OK, $width->getStatusCode());
        self::assertSame('full', $user->getPreferredContentWidth());

        $tour = $controller->productTourSeen(Request::create(
            '/account/product-tour/seen',
            'POST',
            server: ['HTTP_X_CSRF_TOKEN' => 'ok'],
            content: '{"seen":true,"page":"dashboard"}',
        ));
        self::assertSame(Response::HTTP_OK, $tour->getStatusCode());
        self::assertContains('dashboard', $user->getProductTourSeenPages());
        self::assertGreaterThanOrEqual(3, $flush);
    }

    private function controller(User $user, EntityManagerInterface $em, bool $csrfValid): AccountUiPreferencesAjaxController
    {
        $controller = new AccountUiPreferencesAjaxController($em);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(
            static function (CsrfToken $token) use ($csrfValid): bool {
                return $csrfValid && '' !== $token->getValue();
            },
        );
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('security.csrf.token_manager', $csrf);
        $controller->setContainer($container);

        return $controller;
    }
}
