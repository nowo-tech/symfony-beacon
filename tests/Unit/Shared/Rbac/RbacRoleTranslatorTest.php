<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Rbac;

use App\Shared\Rbac\RbacRoleTranslator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RbacRoleTranslatorTest extends TestCase
{
    private TranslatorInterface&MockObject $translator;
    private RbacRoleTranslator $rbacRoleTranslator;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->rbacRoleTranslator = new RbacRoleTranslator($this->translator);
    }

    public function testCatalogSlugLowercasesCode(): void
    {
        self::assertSame('role_support', $this->rbacRoleTranslator->catalogSlug('ROLE_SUPPORT'));
        self::assertSame('org_admin', $this->rbacRoleTranslator->catalogSlug('org_admin'));
    }

    public function testNameKeysFollowCatalogPattern(): void
    {
        self::assertSame('roles.catalog.role_support.name', $this->rbacRoleTranslator->nameKey('ROLE_SUPPORT'));
        self::assertSame(
            'roles.catalog.role_support.description',
            $this->rbacRoleTranslator->descriptionKey('ROLE_SUPPORT'),
        );
    }

    public function testNameReturnsTranslationWhenKeyExists(): void
    {
        $role = $this->role('ROLE_SUPPORT', 'DB Support', 'DB when to use');
        $this->translator->method('trans')
            ->with('roles.catalog.role_support.name')
            ->willReturn('Support desk');

        self::assertSame('Support desk', $this->rbacRoleTranslator->name($role));
    }

    public function testNameFallsBackToEntityWhenMissing(): void
    {
        $role = $this->role('ROLE_CUSTOM', 'Custom role', null);
        $this->translator->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id);

        self::assertSame('Custom role', $this->rbacRoleTranslator->name($role));
    }

    public function testDescriptionReturnsTranslationWhenKeyExists(): void
    {
        $role = $this->role('ROLE_SUPPORT', 'Support', 'DB fallback');
        $this->translator->method('trans')
            ->with('roles.catalog.role_support.description')
            ->willReturn('Use for L1 ticket triage.');

        self::assertSame('Use for L1 ticket triage.', $this->rbacRoleTranslator->description($role));
    }

    public function testDescriptionNullWhenBothMissing(): void
    {
        $role = $this->role('ROLE_EMPTY', 'Empty', null);
        $this->translator->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id);

        self::assertNull($this->rbacRoleTranslator->description($role));
    }

    public function testDescriptionFallsBackToEntityWhenTranslationMissing(): void
    {
        $role = $this->role('ROLE_CUSTOM', 'Custom', '  When operators need a custom bundle.  ');
        $this->translator->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id);

        self::assertSame('When operators need a custom bundle.', $this->rbacRoleTranslator->description($role));
    }

    public function testDescriptionNullWhenFallbackBlank(): void
    {
        $role = $this->role('ROLE_BLANK', 'Blank', '   ');
        $this->translator->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id);

        self::assertNull($this->rbacRoleTranslator->description($role));
    }

    /**
     * @return object{getCode(): string, getName(): string, getDescription(): ?string}
     */
    private function role(string $code, string $name, ?string $description): object
    {
        return new readonly class($code, $name, $description) {
            public function __construct(
                private string $code,
                private string $name,
                private ?string $description,
            ) {
            }

            public function getCode(): string
            {
                return $this->code;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getDescription(): ?string
            {
                return $this->description;
            }
        };
    }
}
