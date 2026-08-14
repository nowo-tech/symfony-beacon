<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminSocialLoginController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class AdminSocialLoginControllerHelpersTest extends TestCase
{
    public function testNullableUrlTrimsAndMapsEmptyToNull(): void
    {
        $controller = new ReflectionClass(AdminSocialLoginController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AdminSocialLoginController::class, 'nullableUrl');

        self::assertNull($method->invoke($controller, null));
        self::assertNull($method->invoke($controller, '   '));
        self::assertSame('https://example.com', $method->invoke($controller, ' https://example.com '));
    }

    public function testParseScopesSplitsAndDeduplicates(): void
    {
        $controller = new ReflectionClass(AdminSocialLoginController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AdminSocialLoginController::class, 'parseScopes');

        self::assertSame([], $method->invoke($controller, '  '));
        self::assertSame(
            ['openid', 'email', 'profile'],
            $method->invoke($controller, 'openid, email  profile,email'),
        );
    }

    public function testUrlsValidForProviderRequiresCustomEndpoints(): void
    {
        $controller = new ReflectionClass(AdminSocialLoginController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AdminSocialLoginController::class, 'urlsValidForProvider');

        self::assertTrue($method->invoke($controller, 'google', [
            'authorize_url' => '',
            'token_url' => '',
            'userinfo_url' => '',
        ]));

        self::assertFalse($method->invoke($controller, 'okta', [
            'authorize_url' => 'https://a',
            'token_url' => '',
            'userinfo_url' => 'https://u',
        ]));

        self::assertTrue($method->invoke($controller, 'okta', [
            'authorize_url' => 'https://a',
            'token_url' => 'https://t',
            'userinfo_url' => 'https://u',
        ]));
    }
}
