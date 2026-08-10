<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Service\InstancePermissionCategoryCatalog;
use PHPUnit\Framework\TestCase;

final class InstancePermissionCategoryCatalogTest extends TestCase
{
    public function testSlugsAreNonEmptyAndIncludeCustom(): void
    {
        $slugs = InstancePermissionCategoryCatalog::slugs();
        self::assertNotEmpty($slugs);
        self::assertContains('custom', $slugs);
        self::assertContains('access', $slugs);
        self::assertContains('issues', $slugs);
        self::assertContains('danger', $slugs);
        self::assertNotContains('identity', $slugs);
        self::assertNotContains('breakglass', $slugs);
    }

    public function testFormChoicesMapTranslationKeysToSlugs(): void
    {
        $choices = InstancePermissionCategoryCatalog::formChoices();
        self::assertSame('access', $choices['permissions.category.access.name']);
        self::assertSame('custom', $choices['permissions.category.custom.name']);
        self::assertArrayNotHasKey('permissions.category.identity.name', $choices);
        self::assertCount(\count(InstancePermissionCategoryCatalog::slugs()), $choices);
    }

    public function testIsKnown(): void
    {
        self::assertTrue(InstancePermissionCategoryCatalog::isKnown('Access'));
        self::assertTrue(InstancePermissionCategoryCatalog::isKnown('access'));
        self::assertFalse(InstancePermissionCategoryCatalog::isKnown('identity'));
        self::assertFalse(InstancePermissionCategoryCatalog::isKnown('unknown'));
    }
}
