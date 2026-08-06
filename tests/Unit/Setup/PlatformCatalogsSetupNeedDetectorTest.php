<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Setup\PlatformCatalogsSetupNeedDetector;
use App\Shared\Settings\Service\PlatformBootstrapState;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class PlatformCatalogsSetupNeedDetectorTest extends TestCase
{
    public function testDisabledAlwaysReturnsFalse(): void
    {
        $detector = new PlatformCatalogsSetupNeedDetector(
            $this->bootstrapNeedingSeed(),
            $this->createStub(AuthorizationCheckerInterface::class),
            enabled: false,
        );

        self::assertFalse($detector->isSetupRequired());
        self::assertSame('platform catalogs missing', $detector->getReason());
    }

    public function testAuthenticatedNonAdminSkipsSeedCheck(): void
    {
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => 'IS_AUTHENTICATED_REMEMBERED' === $attribute,
        );

        $detector = new PlatformCatalogsSetupNeedDetector(
            $this->bootstrapNeedingSeed(),
            $auth,
            enabled: true,
        );

        self::assertFalse($detector->isSetupRequired());
    }

    public function testDelegatesToPlatformBootstrapState(): void
    {
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        $detector = new PlatformCatalogsSetupNeedDetector(
            $this->bootstrapNeedingSeed(),
            $auth,
            enabled: true,
        );

        self::assertTrue($detector->isSetupRequired());
    }

    public function testThrowableFromBootstrapStateReturnsFalse(): void
    {
        $menus = $this->createStub(MenuRepository::class);
        $menus->method('findOneByCodeAndContext')->willThrowException(new RuntimeException('db down'));

        $state = new PlatformBootstrapState(
            $menus,
            $this->createStub(BreadcrumbCollectionRepository::class),
            $this->createStub(CookieConsentConfigRepository::class),
        );

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        $detector = new PlatformCatalogsSetupNeedDetector($state, $auth, enabled: true);

        self::assertFalse($detector->isSetupRequired());
    }

    private function bootstrapNeedingSeed(): PlatformBootstrapState
    {
        $menus = $this->createStub(MenuRepository::class);
        $menus->method('findOneByCodeAndContext')->willReturn(null);

        return new PlatformBootstrapState(
            $menus,
            $this->createStub(BreadcrumbCollectionRepository::class),
            $this->createStub(CookieConsentConfigRepository::class),
        );
    }
}
