<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Navigation\AppSection;
use App\Shared\Navigation\AppSectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AppSectionTest extends TestCase
{
    public function testSectionHelpers(): void
    {
        self::assertSame('dashboard', AppSection::Dashboard->menuCode());
        self::assertSame('dashboard_home', AppSection::Dashboard->homeRoute());
        self::assertSame('nav.dashboard', AppSection::Dashboard->labelKey());

        self::assertSame('account_profile', AppSection::Preferences->homeRoute());
        self::assertSame('nav.preferences', AppSection::Preferences->labelKey());

        self::assertSame('admin_hub', AppSection::Administration->homeRoute());
        self::assertSame('nav.admin', AppSection::Administration->labelKey());
    }

    #[DataProvider('routeProvider')]
    public function testResolverMapsRoutes(?string $route, AppSection $expected): void
    {
        $stack = new RequestStack();
        if (null !== $route) {
            $request = Request::create('/');
            $request->attributes->set('_route', $route);
            $stack->push($request);
        }

        self::assertSame($expected, (new AppSectionResolver($stack))->current());
    }

    /**
     * @return iterable<string, array{?string, AppSection}>
     */
    public static function routeProvider(): iterable
    {
        yield 'no request' => [null, AppSection::Dashboard];
        yield 'empty route' => ['', AppSection::Dashboard];
        yield 'dashboard' => ['dashboard_home', AppSection::Dashboard];
        yield 'account' => ['account_profile', AppSection::Preferences];
        yield 'admin' => ['admin_hub', AppSection::Administration];
        yield 'settings' => ['settings_ops_defaults', AppSection::Administration];
        yield 'http log' => ['nowo_http_log_index', AppSection::Administration];
        yield 'issues' => ['issue_index', AppSection::Dashboard];
    }
}
