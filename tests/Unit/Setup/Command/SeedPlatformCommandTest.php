<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup\Command;

use App\Identity\Repository\InstancePermissionRepository;
use App\Identity\Repository\InstanceRoleRepository;
use App\Identity\Service\InstanceRbacSeeder;
use App\Setup\Command\SeedPlatformCommand;
use App\Setup\Demo\BreadcrumbDemoSeeder;
use App\Setup\Demo\CookieConsentDemoSeeder;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Setup\Demo\DemoFixtureLoader;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedPlatformCommandTest extends TestCase
{
    public function testExecuteSeedsPlatformCatalogs(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');
        $em->method('remove');

        $menus = [];
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneByCodeAndContext')->willReturnCallback(
            static function (string $code) use (&$menus): ?Menu {
                return $menus[$code] ?? null;
            },
        );
        $menuRepo->method('reset');
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$menus): void {
            if ($entity instanceof Menu) {
                $menus[$entity->getCode()] = $entity;
            }
        });

        $breadcrumbs = $this->createStub(BreadcrumbCollectionRepository::class);
        $breadcrumbs->method('findOneByCodeAndContextKey')->willReturn(null);
        $cookieConfigs = $this->createStub(CookieConsentConfigRepository::class);
        $cookieConfigs->method('findDefaultEnabled')->willReturn(null);
        $cookieDefs = $this->createStub(CookieDefinitionRepository::class);
        $cookieDefs->method('findByConfigOrdered')->willReturn([]);

        $permissionRepo = $this->createStub(InstancePermissionRepository::class);
        $permissionRepo->method('findOneByKey')->willReturn(null);
        $permissionRepo->method('findAll')->willReturn([]);
        $roleRepo = $this->createStub(InstanceRoleRepository::class);
        $roleRepo->method('findOneByCode')->willReturn(null);
        $roleRepo->method('findAll')->willReturn([]);

        $loader = new DemoFixtureLoader();
        $command = new SeedPlatformCommand(
            new DashboardMenuDemoSeeder($em, $menuRepo, $loader),
            new BreadcrumbDemoSeeder($em, $breadcrumbs, $loader),
            new CookieConsentDemoSeeder($em, $cookieConfigs, $cookieDefs, $loader),
            new InstanceRbacSeeder($permissionRepo, $roleRepo, $em),
        );

        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('breadcrumb', strtolower($tester->getDisplay()));
        self::assertStringContainsString('navigation', strtolower($tester->getDisplay()));
        self::assertStringContainsString('cookie consent', strtolower($tester->getDisplay()));
    }

    public function testExecuteReportsFailureOnThrowable(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneByCodeAndContext')->willThrowException(new \RuntimeException('db down'));
        $loader = new DemoFixtureLoader();
        $em = $this->createStub(EntityManagerInterface::class);

        $command = new SeedPlatformCommand(
            new DashboardMenuDemoSeeder($em, $menuRepo, $loader),
            new BreadcrumbDemoSeeder($em, $this->createStub(BreadcrumbCollectionRepository::class), $loader),
            new CookieConsentDemoSeeder(
                $em,
                $this->createStub(CookieConsentConfigRepository::class),
                $this->createStub(CookieDefinitionRepository::class),
                $loader,
            ),
            new InstanceRbacSeeder(
                $this->createStub(InstancePermissionRepository::class),
                $this->createStub(InstanceRoleRepository::class),
                $em,
            ),
        );

        $tester = new CommandTester($command);
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('Platform seed failed', $tester->getDisplay());
    }
}
