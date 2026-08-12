<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Menu;

use App\Shared\Menu\SecurityIsGrantedMenuPermissionChecker;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class SecurityIsGrantedMenuPermissionCheckerTest extends TestCase
{
    public function testEmptyPermissionKeysAreVisible(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('isGranted');

        $item = new MenuItem();
        $item->setPermissionKeys([]);

        self::assertTrue(new SecurityIsGrantedMenuPermissionChecker($security)->canView($item));
    }

    public function testVisibleWhenAnyKeyGranted(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $key): bool => 'ROLE_ADMIN' === $key,
        );

        $item = new MenuItem();
        $item->setPermissionKeys(['ROLE_USER', 'ROLE_ADMIN']);

        self::assertTrue(new SecurityIsGrantedMenuPermissionChecker($security)->canView($item));
    }

    public function testHiddenWhenNoKeyGranted(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);

        $item = new MenuItem();
        $item->setPermissionKeys(['ROLE_ADMIN']);

        self::assertFalse(new SecurityIsGrantedMenuPermissionChecker($security)->canView($item));
    }
}
