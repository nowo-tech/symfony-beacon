<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security;

use App\Identity\Security\Permission;
use App\Project\Service\ProjectPermissionCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermissionTest extends TestCase
{
    #[DataProvider('dottedCapabilityCases')]
    public function testIsDottedCapability(string $attribute, bool $expected): void
    {
        self::assertSame($expected, Permission::isDottedCapability($attribute));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function dottedCapabilityCases(): iterable
    {
        yield 'project view' => ['project.view', true];
        yield 'no dot' => ['project', false];
        yield 'ROLE_' => ['ROLE_ADMIN', false];
        yield 'ROLE_ with dot' => ['ROLE_FOO.BAR', false];
        yield 'IS_' => ['IS_AUTHENTICATED', false];
        yield 'IS_ with dot' => ['IS_FOO.BAR', false];
    }

    public function testAllValuesMatchCatalogKeys(): void
    {
        $fromCatalog = array_values(array_unique(array_map(
            static fn (array $definition): string => $definition['key'],
            ProjectPermissionCatalog::definitions(),
        )));

        self::assertSame($fromCatalog, Permission::allValues());
        self::assertNotEmpty(Permission::allValues());
    }

    public function testIsKnownNormalizesCaseAndWhitespace(): void
    {
        $key = Permission::allValues()[0];
        self::assertTrue(Permission::isKnown($key));
        self::assertTrue(Permission::isKnown('  '.strtoupper($key).'  '));
        self::assertFalse(Permission::isKnown('not.a.real.permission'));
    }
}
