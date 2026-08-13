<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Entity\InstancePermission;
use App\Identity\Repository\InstancePermissionRepository;
use App\Identity\Service\InstancePermissionMatrixBuilder;
use PHPUnit\Framework\TestCase;

final class InstancePermissionMatrixBuilderTest extends TestCase
{
    public function testGroupsPermissionsByCategoryPreservingOrder(): void
    {
        $a = (new InstancePermission())->setKey('project.view')->setCategory('project');
        $b = (new InstancePermission())->setKey('project.delete')->setCategory('project');
        $c = (new InstancePermission())->setKey('admin.users')->setCategory('admin');

        $repository = $this->createStub(InstancePermissionRepository::class);
        $repository->method('findAllOrdered')->willReturn([$a, $b, $c]);

        $matrix = new InstancePermissionMatrixBuilder($repository)->permissionsByCategory();

        self::assertSame(['project', 'admin'], array_keys($matrix));
        self::assertSame([$a, $b], $matrix['project']);
        self::assertSame([$c], $matrix['admin']);
    }

    public function testEmptyCatalog(): void
    {
        $repository = $this->createStub(InstancePermissionRepository::class);
        $repository->method('findAllOrdered')->willReturn([]);

        self::assertSame([], new InstancePermissionMatrixBuilder($repository)->permissionsByCategory());
    }
}
