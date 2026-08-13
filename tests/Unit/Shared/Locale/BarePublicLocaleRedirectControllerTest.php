<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Locale;

use App\Shared\Locale\BarePublicLocaleRedirectController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BarePublicLocaleRedirectControllerTest extends TestCase
{
    public function testLegalAndHomeRedirects(): void
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $name, array $params = []): string {
            if ('nowo_auth_kit_login_unlocalized' === $name) {
                return '/login';
            }

            return '/'.($params['_locale'] ?? 'en').'/'.str_replace('legal_', 'legal/', $name);
        });

        $container = new Container();
        $container->set('router', $urls);
        $controller = new BarePublicLocaleRedirectController('en');
        $controller->setContainer($container);

        self::assertSame('/en/legal/notice', $controller->legalNotice()->getTargetUrl());
        self::assertSame('/en/legal/privacy', $controller->legalPrivacy()->getTargetUrl());
        self::assertSame('/en/legal/terms', $controller->legalTerms()->getTargetUrl());
        self::assertSame('/en/legal/cookies', $controller->legalCookies()->getTargetUrl());
        self::assertSame('/login', $controller->home()->getTargetUrl());
    }
}
