<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Menu;

use App\Shared\Menu\AdministrationMenuCurrentMatcher;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AdministrationMenuCurrentMatcherTest extends TestCase
{
    #[Test]
    public function marksKitPrefixRoutes(): void
    {
        $matcher = new AdministrationMenuCurrentMatcher();
        $item = new MenuItem();
        $item->setRouteName('nowo_http_log_admin_index');
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);

        $request = Request::create('/admin/http-log/1');
        $request->attributes->set('_route', 'nowo_http_log_admin_show');

        self::assertTrue($matcher->isCurrent($item, $request, '/admin/http-log'));
    }

    #[Test]
    public function marksOpsDefaultsAndAppearanceOnSectionRoutes(): void
    {
        $matcher = new AdministrationMenuCurrentMatcher();

        $ops = new MenuItem();
        $ops->setRouteName('admin_ops_defaults');
        $opsRequest = Request::create('/admin/ops-defaults/governance');
        $opsRequest->attributes->set('_route', 'admin_ops_defaults_section');
        self::assertTrue($matcher->isCurrent($ops, $opsRequest, '/admin/ops-defaults'));

        $appearance = new MenuItem();
        $appearance->setRouteName('admin_appearance');
        self::assertFalse($matcher->isCurrent($appearance, $opsRequest, '/admin/appearance'));

        $appearanceRequest = Request::create('/admin/appearance/brand');
        $appearanceRequest->attributes->set('_route', 'admin_appearance_section');
        self::assertTrue($matcher->isCurrent($appearance, $appearanceRequest, '/admin/appearance'));
        self::assertFalse($matcher->isCurrent($ops, $appearanceRequest, '/admin/ops-defaults'));
    }
}
