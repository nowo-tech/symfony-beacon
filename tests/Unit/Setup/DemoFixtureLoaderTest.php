<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Setup\Demo\DemoFixtureLoader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DemoFixtureLoaderTest extends TestCase
{
    public function testLoadsBreadcrumbsFixture(): void
    {
        $loader = new DemoFixtureLoader();
        $data = $loader->load('breadcrumbs.default.json');

        self::assertArrayHasKey('collection', $data);
        self::assertArrayHasKey('items', $data);
        self::assertIsArray($data['items']);
        self::assertNotEmpty($data['items']);
    }

    public function testLoadsMenusAndCookieConsentFixtures(): void
    {
        $loader = new DemoFixtureLoader();
        $menus = $loader->load('menus.json');
        $cookies = $loader->load('cookie_consent.default.json');

        self::assertArrayHasKey('menus', $menus);
        self::assertNotEmpty($menus['menus']);
        self::assertArrayHasKey('cookies', $cookies);
    }

    public function testMissingFixtureThrows(): void
    {
        $loader = new DemoFixtureLoader();

        $this->expectException(InvalidArgumentException::class);
        $loader->load('does-not-exist.json');
    }
}
