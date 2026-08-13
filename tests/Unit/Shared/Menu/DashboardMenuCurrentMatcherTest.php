<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Menu;

use App\Shared\Menu\DashboardMenuCurrentMatcher;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class DashboardMenuCurrentMatcherTest extends TestCase
{
    #[Test]
    #[DataProvider('projectSurfaceRoutes')]
    public function marksProjectsItemOnProjectSurfaces(string $route): void
    {
        $matcher = new DashboardMenuCurrentMatcher();
        $item = new MenuItem();
        $item->setRouteName('dashboard_home');
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);

        $request = Request::create('/projects/019fea2d-507b-7890-8b33-ca488db6f696');
        $request->attributes->set('_route', $route);

        self::assertTrue($matcher->isCurrent($item, $request, '/'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function projectSurfaceRoutes(): iterable
    {
        yield 'home' => ['dashboard_home'];
        yield 'show' => ['project_show'];
        yield 'settings' => ['project_settings'];
        yield 'settings section' => ['project_settings_section'];
        yield 'issues' => ['issue_index'];
        yield 'issue show' => ['issue_show'];
        yield 'performance' => ['performance_index'];
        yield 'analytics' => ['analytics_show'];
        yield 'releases' => ['project_releases'];
        yield 'event' => ['event_show'];
    }

    #[Test]
    public function doesNotMarkProjectsForOtherDashboardRoutes(): void
    {
        $matcher = new DashboardMenuCurrentMatcher();
        $item = new MenuItem();
        $item->setRouteName('dashboard_home');

        $request = Request::create('/assignments');
        $request->attributes->set('_route', 'dashboard_assignments');

        self::assertFalse($matcher->isCurrent($item, $request, '/'));
    }
}
