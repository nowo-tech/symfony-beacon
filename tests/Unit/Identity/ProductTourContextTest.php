<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Tour\ProductTourContext;
use App\Identity\Tour\ProductTourPage;
use App\Project\Enum\ProjectRole;
use PHPUnit\Framework\TestCase;

final class ProductTourContextTest extends TestCase
{
    public function testPageAllAndPermissionHelpers(): void
    {
        self::assertSame(ProductTourPage::cases(), ProductTourPage::all());

        $withoutRole = new ProductTourContext(
            ProductTourPage::Dashboard,
            isInstanceAdmin: false,
            canCreateProject: true,
        );
        self::assertFalse($withoutRole->canTriageIssues());
        self::assertFalse($withoutRole->canManageProject());

        $member = new ProductTourContext(
            ProductTourPage::ProjectIssues,
            isInstanceAdmin: false,
            canCreateProject: false,
            projectRole: ProjectRole::Member,
        );
        self::assertTrue($member->canTriageIssues());
        self::assertFalse($member->canManageProject());

        $admin = new ProductTourContext(
            ProductTourPage::Admin,
            isInstanceAdmin: true,
            canCreateProject: true,
            projectRole: ProjectRole::Admin,
        );
        self::assertTrue($admin->canTriageIssues());
        self::assertTrue($admin->canManageProject());
    }
}
