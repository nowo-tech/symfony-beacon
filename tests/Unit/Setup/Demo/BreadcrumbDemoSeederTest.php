<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup\Demo;

use App\Setup\Demo\BreadcrumbDemoSeeder;
use App\Setup\Demo\DemoFixtureLoader;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use PHPUnit\Framework\TestCase;

final class BreadcrumbDemoSeederTest extends TestCase
{
    public function testSeedIfEmptyCreatesDefaultCollectionFromFixture(): void
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $flush = 0;
        $em->method('flush')->willReturnCallback(static function () use (&$flush): void {
            ++$flush;
        });

        $collections = $this->createStub(BreadcrumbCollectionRepository::class);
        $collections->method('findOneByCodeAndContextKey')->willReturn(null);

        $seeder = new BreadcrumbDemoSeeder($em, $collections, new DemoFixtureLoader());
        self::assertTrue($seeder->seedIfEmpty());
        self::assertSame(1, $flush);
        self::assertNotEmpty(array_filter($persisted, static fn (object $e): bool => $e instanceof BreadcrumbCollection));
    }

    public function testSeedIfEmptyIsNoOpWhenItemsAlreadyMatch(): void
    {
        $loader = new DemoFixtureLoader();
        $fixture = $loader->load('breadcrumbs.default.json');
        /** @var array{code: string, contextKey: string, name: string, separatorIcon: string, classList: string, classItem: string, classSeparator: string, classCurrent: string, responsive: array} $collectionData */
        $collectionData = $fixture['collection'];
        $collection = new BreadcrumbCollection();
        $collection->setCode($collectionData['code']);
        $collection->setContextKey($collectionData['contextKey']);
        $collection->setName($collectionData['name']);
        $collection->setSeparatorIcon($collectionData['separatorIcon']);
        $collection->setClassList($collectionData['classList']);
        $collection->setClassItem($collectionData['classItem']);
        $collection->setClassSeparator($collectionData['classSeparator']);
        $collection->setClassCurrent($collectionData['classCurrent']);
        $collection->setResponsiveConfig($collectionData['responsive']);

        // Pre-create items in fixture order so ensureItem finds matches.
        $itemsByRoute = [];
        foreach ($fixture['items'] as $itemData) {
            $item = new BreadcrumbItem();
            $item->setRouteName($itemData['route']);
            $item->setLabel($itemData['label']);
            $item->setTranslations($itemData['translations']);
            $item->setDynamicParamKeys($itemData['dynamicParamKeys']);
            $item->setStaticRouteParams([]);
            $item->setLinkEnabled(true);
            if (null !== ($itemData['parent'] ?? null)) {
                $item->setParent($itemsByRoute[$itemData['parent']]);
            }
            $collection->addItem($item);
            $itemsByRoute[$itemData['route']] = $item;
        }

        $collections = $this->createStub(BreadcrumbCollectionRepository::class);
        $collections->method('findOneByCodeAndContextKey')->willReturn($collection);
        $flush = 0;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willReturnCallback(static function () use (&$flush): void {
            ++$flush;
        });

        self::assertFalse(new BreadcrumbDemoSeeder($em, $collections, $loader)->seedIfEmpty());
        self::assertSame(0, $flush);
    }
}
