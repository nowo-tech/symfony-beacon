<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Rbac;

use App\Shared\Rbac\RbacPermissionTranslator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RbacPermissionTranslatorTest extends TestCase
{
    private TranslatorInterface&MockObject $translator;
    private RbacPermissionTranslator $rbacPermissionTranslator;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('getLocale')->willReturn('es');
        $this->rbacPermissionTranslator = new RbacPermissionTranslator($this->translator);
    }

    public function testCatalogSlugReplacesDots(): void
    {
        self::assertSame(
            'project_view',
            $this->rbacPermissionTranslator->catalogSlug('project.view'),
        );
    }

    public function testNameKeysFollowCatalogPattern(): void
    {
        self::assertSame(
            'permissions.catalog.project_settings_manage.name',
            $this->rbacPermissionTranslator->nameKey('project.settings.manage'),
        );
        self::assertSame(
            'permissions.catalog.project_settings_manage.description',
            $this->rbacPermissionTranslator->descriptionKey('project.settings.manage'),
        );
    }

    public function testNamePrefersTranslatableRow(): void
    {
        $permission = $this->permission(
            'project.view',
            'DB View',
            'DB desc',
            ['es' => 'Ver proyecto (BD)'],
            [],
        );
        $this->translator->expects(self::never())->method('trans');

        self::assertSame('Ver proyecto (BD)', $this->rbacPermissionTranslator->name($permission));
    }

    public function testNameReturnsYamlWhenDbLocaleMissing(): void
    {
        $permission = $this->permission('project.view', 'DB View', 'DB desc');
        $this->translator->method('trans')
            ->with('permissions.catalog.project_view.name')
            ->willReturn('View project');

        self::assertSame('View project', $this->rbacPermissionTranslator->name($permission));
    }

    public function testNameFallsBackToEntityWhenMissing(): void
    {
        $permission = $this->permission('custom.thing.manage', 'Custom permission', null);
        $this->translator->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id);

        self::assertSame('Custom permission', $this->rbacPermissionTranslator->name($permission));
    }

    public function testEmptyKeySkipsCatalogLookup(): void
    {
        $permission = $this->permission('', '', null);
        $this->translator->expects(self::never())->method('trans');

        self::assertSame('', $this->rbacPermissionTranslator->nameKey(''));
        self::assertSame('', $this->rbacPermissionTranslator->name($permission));
        self::assertNull($this->rbacPermissionTranslator->description($permission));
    }

    public function testDescriptionPrefersTranslatableRow(): void
    {
        $permission = $this->permission(
            'project.issues.triage',
            'Triage',
            'DB fallback',
            [],
            ['es' => 'Triaje (BD)'],
        );
        $this->translator->expects(self::never())->method('trans');

        self::assertSame('Triaje (BD)', $this->rbacPermissionTranslator->description($permission));
    }

    public function testDescriptionReturnsYamlWhenDbLocaleMissing(): void
    {
        $permission = $this->permission('project.issues.triage', 'Triage', 'DB fallback');
        $this->translator->method('trans')
            ->with('permissions.catalog.project_issues_triage.description')
            ->willReturn('Mutate issues within a project.');

        self::assertSame(
            'Mutate issues within a project.',
            $this->rbacPermissionTranslator->description($permission),
        );
    }

    public function testDescriptionFallsBackToEntityWhenTranslationMissing(): void
    {
        $permission = $this->permission('custom.thing.manage', 'Custom', '  Entity description.  ');
        $this->translator->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id);

        self::assertSame('Entity description.', $this->rbacPermissionTranslator->description($permission));
    }

    /**
     * @param array<string, string> $nameTranslations
     * @param array<string, string> $descriptionTranslations
     *
     * @return object{
     *     getKey(): string,
     *     getName(): string,
     *     getDescription(): ?string,
     *     getNameForLocale(string): ?string,
     *     getDescriptionForLocale(string): ?string
     * }
     */
    private function permission(
        string $key,
        string $name,
        ?string $description,
        array $nameTranslations = [],
        array $descriptionTranslations = [],
    ): object {
        return new class($key, $name, $description, $nameTranslations, $descriptionTranslations) {
            /**
             * @param array<string, string> $nameTranslations
             * @param array<string, string> $descriptionTranslations
             */
            public function __construct(
                private readonly string $key,
                private readonly string $name,
                private readonly ?string $description,
                private readonly array $nameTranslations,
                private readonly array $descriptionTranslations,
            ) {
            }

            public function getKey(): string
            {
                return $this->key;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getDescription(): ?string
            {
                return $this->description;
            }

            public function getNameForLocale(string $locale): ?string
            {
                $value = $this->nameTranslations[$locale] ?? null;

                return \is_string($value) && '' !== trim($value) ? trim($value) : null;
            }

            public function getDescriptionForLocale(string $locale): ?string
            {
                $value = $this->descriptionTranslations[$locale] ?? null;

                return \is_string($value) && '' !== trim($value) ? trim($value) : null;
            }
        };
    }
}
