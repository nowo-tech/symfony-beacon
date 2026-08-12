<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Rbac;

use App\Identity\Entity\InstancePermission;
use App\Shared\Rbac\RbacPermissionCategoryTranslator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RbacPermissionCategoryTranslatorTest extends TestCase
{
    private TranslatorInterface&MockObject $translator;
    private RbacPermissionCategoryTranslator $rbacPermissionCategoryTranslator;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->rbacPermissionCategoryTranslator = new RbacPermissionCategoryTranslator($this->translator);
    }

    public function testCatalogSlugNormalizesCase(): void
    {
        self::assertSame('identity', $this->rbacPermissionCategoryTranslator->catalogSlug(' Identity '));
    }

    public function testNameKeysFollowCategoryPattern(): void
    {
        self::assertSame(
            'permissions.category.identity.name',
            $this->rbacPermissionCategoryTranslator->nameKey('identity'),
        );
        self::assertSame(
            'permissions.category.identity.description',
            $this->rbacPermissionCategoryTranslator->descriptionKey('identity'),
        );
    }

    public function testNameReturnsTranslationWhenKeyExists(): void
    {
        $this->translator->expects(self::any())
            ->method('trans')
            ->with('permissions.category.org.name')
            ->willReturn('Organization');

        self::assertSame('Organization', $this->rbacPermissionCategoryTranslator->name('org'));
    }

    public function testNameFallsBackToSlugWhenMissing(): void
    {
        $this->translator->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id);

        self::assertSame('custom', $this->rbacPermissionCategoryTranslator->name('custom'));
    }

    public function testNameAcceptsPermissionObject(): void
    {
        $permission = new InstancePermission();
        $permission->setKey('platform.x');
        $permission->setName('Platform X');
        $permission->setCategory('platform');
        $this->translator->expects(self::any())
            ->method('trans')
            ->with('permissions.category.platform.name')
            ->willReturn('Platform');

        self::assertSame('Platform', $this->rbacPermissionCategoryTranslator->name($permission));
    }

    public function testDescriptionReturnsTranslationWhenKeyExists(): void
    {
        $this->translator->expects(self::any())
            ->method('trans')
            ->with('permissions.category.navigation.description')
            ->willReturn('Menus, breadcrumbs, and cookie consent.');

        self::assertSame(
            'Menus, breadcrumbs, and cookie consent.',
            $this->rbacPermissionCategoryTranslator->description('navigation'),
        );
    }

    public function testDescriptionReturnsNullWhenMissing(): void
    {
        $this->translator->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id);

        self::assertNull($this->rbacPermissionCategoryTranslator->description('unknown'));
    }
}
