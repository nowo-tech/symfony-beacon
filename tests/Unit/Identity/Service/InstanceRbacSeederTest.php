<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\InstanceRole;
use App\Identity\Repository\InstancePermissionRepository;
use App\Identity\Repository\InstanceRoleRepository;
use App\Identity\Service\InstanceRbacSeeder;
use App\Identity\Service\InstanceRoleCatalog;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class InstanceRbacSeederTest extends TestCase
{
    public function testSeedIfEmptyCreatesCatalogAndRemovesLegacy(): void
    {
        $permissions = [];
        $roles = [];
        $removed = [];
        $flushed = 0;

        $permissionRepo = $this->createStub(InstancePermissionRepository::class);
        $permissionRepo->method('findOneByKey')->willReturnCallback(
            static function (string $key) use (&$permissions): ?InstancePermission {
                return $permissions[$key] ?? null;
            },
        );
        $permissionRepo->method('findAll')->willReturnCallback(static function () use (&$permissions): array {
            return array_values($permissions);
        });

        $roleRepo = $this->createStub(InstanceRoleRepository::class);
        $roleRepo->method('findOneByCode')->willReturnCallback(
            static function (string $code) use (&$roles): ?InstanceRole {
                return $roles[$code] ?? null;
            },
        );
        $roleRepo->method('findAll')->willReturnCallback(static function () use (&$roles): array {
            return array_values($roles);
        });

        $legacy = new InstanceRole();
        $legacy->setCode(InstanceRoleCatalog::legacyOperatorCodes()[0] ?? 'operator');
        $roles[$legacy->getCode()] = $legacy;

        $obsolete = new InstancePermission();
        $obsolete->setKey('admin.legacy');
        $obsolete->setName('Legacy');
        $obsolete->setDescription('x');
        $obsolete->setCategory('admin');
        $permissions['admin.legacy'] = $obsolete;

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$permissions, &$roles): void {
            if ($entity instanceof InstancePermission) {
                $permissions[$entity->getKey()] = $entity;
            }
            if ($entity instanceof InstanceRole) {
                $roles[$entity->getCode()] = $entity;
            }
        });
        $em->method('remove')->willReturnCallback(static function (object $entity) use (&$permissions, &$roles, &$removed): void {
            $removed[] = $entity;
            if ($entity instanceof InstancePermission) {
                unset($permissions[$entity->getKey()]);
            }
            if ($entity instanceof InstanceRole) {
                unset($roles[$entity->getCode()]);
            }
        });
        $em->method('flush')->willReturnCallback(static function () use (&$flushed): void {
            ++$flushed;
        });

        $seeder = new InstanceRbacSeeder($permissionRepo, $roleRepo, $em);
        self::assertTrue($seeder->seedIfEmpty());
        self::assertGreaterThanOrEqual(1, $flushed);
        self::assertNotContains($obsolete, array_values($permissions));
        self::assertArrayNotHasKey($legacy->getCode(), $roles);
        self::assertNotEmpty($permissions);
        self::assertNotEmpty($roles);
    }

    public function testRepeatedSeedKeepsCatalogPopulated(): void
    {
        $permissions = [];
        $roles = [];
        $permissionRepo = $this->createStub(InstancePermissionRepository::class);
        $permissionRepo->method('findOneByKey')->willReturnCallback(
            static function (string $key) use (&$permissions): ?InstancePermission {
                return $permissions[$key] ?? null;
            },
        );
        $permissionRepo->method('findAll')->willReturnCallback(static fn (): array => array_values($permissions));
        $roleRepo = $this->createStub(InstanceRoleRepository::class);
        $roleRepo->method('findOneByCode')->willReturnCallback(
            static function (string $code) use (&$roles): ?InstanceRole {
                return $roles[$code] ?? null;
            },
        );
        $roleRepo->method('findAll')->willReturnCallback(static fn (): array => array_values($roles));
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$permissions, &$roles): void {
            if ($entity instanceof InstancePermission) {
                $permissions[$entity->getKey()] = $entity;
            }
            if ($entity instanceof InstanceRole) {
                $roles[$entity->getCode()] = $entity;
            }
        });

        $seeder = new InstanceRbacSeeder($permissionRepo, $roleRepo, $em);
        self::assertTrue($seeder->seedIfEmpty());
        $permissionCount = \count($permissions);
        $roleCount = \count($roles);
        $seeder->seedIfEmpty();
        self::assertSame($permissionCount, \count($permissions));
        self::assertSame($roleCount, \count($roles));
        self::assertGreaterThan(0, $permissionCount);
        self::assertGreaterThan(0, $roleCount);
    }
}
