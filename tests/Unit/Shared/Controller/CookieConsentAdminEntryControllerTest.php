<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Controller;

use App\Shared\Controller\CookieConsentAdminEntryController;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CookieConsentAdminEntryControllerTest extends TestCase
{
    public function testRedirectsToEditWhenDefaultConfigExists(): void
    {
        $config = new CookieConsentConfig()->setName('default')->setDefault(true)->setEnabled(true);
        new ReflectionProperty(CookieConsentConfig::class, 'id')->setValue($config, 7);

        $repo = $this->createStub(CookieConsentConfigRepository::class);
        $repo->method('findDefaultEnabled')->willReturn($config);

        $controller = $this->controller($repo, static function (string $name, array $params = []): string {
            self::assertSame('nowo_cookie_consent_config_settings_edit', $name);
            self::assertSame(7, $params['configId']);

            return '/admin/cookie-consent/7/edit';
        });

        $response = $controller();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/cookie-consent/7/edit', $response->getTargetUrl());
    }

    public function testRedirectsToHubWhenMissing(): void
    {
        $repo = $this->createStub(CookieConsentConfigRepository::class);
        $repo->method('findDefaultEnabled')->willReturn(null);

        $controller = $this->controller($repo, static function (string $name): string {
            self::assertSame('admin_hub', $name);

            return '/admin';
        });

        $response = $controller();
        self::assertSame('/admin', $response->getTargetUrl());
    }

    /**
     * @param callable(string, array<string, mixed>): string $generate
     */
    private function controller(CookieConsentConfigRepository $repo, callable $generate): CookieConsentAdminEntryController
    {
        $controller = new CookieConsentAdminEntryController($repo);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback($generate);

        $requestStack = new RequestStack();
        $request = Request::create('/admin/cookie-consent');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);
        $requestStack->push($request);

        $container = new Container();
        $container->set('router', $urls);
        $container->set('request_stack', $requestStack);
        $controller->setContainer($container);

        return $controller;
    }
}
