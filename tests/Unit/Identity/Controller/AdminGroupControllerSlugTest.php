<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminGroupController;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AdminGroupControllerSlugTest extends TestCase
{
    public function testSlugifyNormalizesAsciiNames(): void
    {
        $controller = new ReflectionClass(AdminGroupController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AdminGroupController::class, 'slugify');

        self::assertSame('acme-ops', $method->invoke($controller, 'Acme Ops'));
        self::assertSame('hello-world', $method->invoke($controller, 'Hello World!'));
    }

    public function testSlugifyFallsBackWhenNameIsEmptyAfterSlug(): void
    {
        $controller = new ReflectionClass(AdminGroupController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AdminGroupController::class, 'slugify');
        $slug = $method->invoke($controller, '@@@');

        self::assertMatchesRegularExpression('/^group-[0-9a-f]{6}$/', $slug);
    }

    public function testUniqueSlugAppendsSuffixOnCollision(): void
    {
        $existing = new UserGroup()->setName('Ops')->setSlug('ops');
        new ReflectionProperty(UserGroup::class, 'id')->setValue($existing, 10);

        $groups = $this->createStub(UserGroupRepository::class);
        $groups->method('findOneBySlug')->willReturnCallback(
            static fn (string $slug): ?UserGroup => match ($slug) {
                'ops', 'ops-2' => $existing,
                default => null,
            },
        );

        $controller = new ReflectionClass(AdminGroupController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminGroupController::class, 'groupRepository')->setValue($controller, $groups);

        $method = new ReflectionMethod(AdminGroupController::class, 'uniqueSlug');
        self::assertSame('ops-3', $method->invoke($controller, 'Ops'));
    }

    public function testUniqueSlugAllowsExceptedGroupToKeepSlug(): void
    {
        $group = new UserGroup()->setName('Ops')->setSlug('ops');
        new ReflectionProperty(UserGroup::class, 'id')->setValue($group, 7);

        $groups = $this->createStub(UserGroupRepository::class);
        $groups->method('findOneBySlug')->willReturn($group);

        $controller = new ReflectionClass(AdminGroupController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminGroupController::class, 'groupRepository')->setValue($controller, $groups);

        $method = new ReflectionMethod(AdminGroupController::class, 'uniqueSlug');
        self::assertSame('ops', $method->invoke($controller, 'Ops', $group));
    }
}
