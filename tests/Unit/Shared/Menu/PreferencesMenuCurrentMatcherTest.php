<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Menu;

use App\Shared\Menu\PreferencesMenuCurrentMatcher;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PreferencesMenuCurrentMatcherTest extends TestCase
{
    public function testMarksDisplayCurrentForTourSubRoute(): void
    {
        $matcher = new PreferencesMenuCurrentMatcher();

        $display = new MenuItem();
        $display->setRouteName('account_display');
        $security = new MenuItem();
        $security->setRouteName('account_security');

        $request = Request::create('/account/display/tours');
        $request->attributes->set('_route', 'account_display_tours');

        self::assertFalse($matcher->isCurrent($security, $request, '/account/security'));
        self::assertTrue($matcher->isCurrent($display, $request, '/account/display'));
    }
}
