<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup\Demo;

use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Setup\Demo\DemoFixtureLoader;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use PHPUnit\Framework\TestCase;

final class DashboardMenuDemoSeederTest extends TestCase
{
    public function testSeedIfEmptyCreatesSectionMenusAndRemovesLegacyMain(): void
    {
        /** @var array<string, Menu> $menus */
        $menus = [];
        $legacy = new Menu();
        $legacy->setCode('main');
        $legacy->setName('Legacy');
        $menus['main'] = $legacy;

        $removed = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$menus): void {
            if ($entity instanceof Menu) {
                $menus[$entity->getCode()] = $entity;
            }
        });
        $em->method('remove')->willReturnCallback(static function (object $entity) use (&$removed, &$menus): void {
            $removed[] = $entity;
            if ($entity instanceof Menu) {
                unset($menus[$entity->getCode()]);
            }
        });
        $flush = 0;
        $em->method('flush')->willReturnCallback(static function () use (&$flush): void {
            ++$flush;
        });

        $repo = $this->createStub(MenuRepository::class);
        $repo->method('findOneByCodeAndContext')->willReturnCallback(
            static function (string $code) use (&$menus): ?Menu {
                return $menus[$code] ?? null;
            },
        );
        $repo->method('reset');

        $seeder = new DashboardMenuDemoSeeder($em, $repo, new DemoFixtureLoader());
        self::assertTrue($seeder->seedIfEmpty());
        self::assertSame(1, $flush);
        self::assertArrayHasKey('dashboard', $menus);
        self::assertArrayHasKey('preferences', $menus);
        self::assertArrayHasKey('administration', $menus);
        self::assertArrayNotHasKey('main', $menus);
        self::assertContains($legacy, $removed);
    }
}
