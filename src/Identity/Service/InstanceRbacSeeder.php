<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\InstanceRole;
use App\Identity\Entity\User;
use App\Identity\Repository\InstancePermissionRepository;
use App\Identity\Repository\InstanceRoleRepository;
use App\Project\Service\ProjectPermissionCatalog;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Idempotent upsert of built-in project permission catalog rows and project-mirror InstanceRoles.
 *
 * Also removes legacy Administration-operator InstanceRoles and obsolete {@code admin.*} catalog rows.
 */
final readonly class InstanceRbacSeeder
{
    public function __construct(
        private InstancePermissionRepository $permissionRepository,
        private InstanceRoleRepository $roleRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return bool true when at least one permission or role row was created, updated, or removed
     */
    public function seedIfEmpty(): bool
    {
        $changed = $this->removeObsoleteAdminPermissions();
        $changed = $this->seedPermissions() || $changed;
        // Persist catalog rows before role matrices query them via findAll().
        if ($changed) {
            $this->entityManager->flush();
        }

        $changed = $this->removeLegacyOperatorRoles() || $changed;
        $changed = $this->seedRoles() || $changed;

        if ($changed) {
            $this->entityManager->flush();
        }

        return $changed;
    }

    private function seedPermissions(): bool
    {
        $changed = false;

        foreach (ProjectPermissionCatalog::definitions() as $definition) {
            $permission = $this->permissionRepository->findOneByKey($definition['key']);
            if (!$permission instanceof InstancePermission) {
                $permission = new InstancePermission();
                $permission->setKey($definition['key']);
                $this->entityManager->persist($permission);
                $changed = true;
            }

            if (
                $permission->getName() !== $definition['name']
                || $permission->getDescription() !== $definition['description']
                || $permission->getCategory() !== $definition['category']
                || !$permission->isSystem()
            ) {
                $permission->setName($definition['name']);
                $permission->setDescription($definition['description']);
                $permission->setCategory($definition['category']);
                $permission->setSystem(true);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Drop built-in {@code admin.*} rows left from earlier catalog seeds (Administration is ROLE_ADMIN-only).
     */
    private function removeObsoleteAdminPermissions(): bool
    {
        $changed = false;

        foreach ($this->permissionRepository->findAll() as $permission) {
            $key = $permission->getKey();
            if (!str_starts_with($key, 'admin.')) {
                continue;
            }

            foreach ($this->roleRepository->findAll() as $role) {
                if ($role->getPermissions()->contains($permission)) {
                    $role->removePermission($permission);
                    $changed = true;
                }
            }

            $this->entityManager->remove($permission);
            $changed = true;
        }

        return $changed;
    }

    private function seedRoles(): bool
    {
        $changed = false;
        /** @var array<string, InstancePermission> $byKey */
        $byKey = [];
        foreach ($this->permissionRepository->findAll() as $permission) {
            $byKey[$permission->getKey()] = $permission;
        }

        foreach (InstanceRoleCatalog::definitions() as $definition) {
            $role = $this->roleRepository->findOneByCode($definition['code']);
            if (!$role instanceof InstanceRole) {
                $role = new InstanceRole();
                $role->setCode($definition['code']);
                $this->entityManager->persist($role);
                $changed = true;
            }

            if (
                $role->getName() !== $definition['name']
                || $role->getDescription() !== $definition['description']
                || !$role->isEnabled()
                || !$role->isSystem()
            ) {
                $role->setName($definition['name']);
                $role->setDescription($definition['description']);
                $role->setEnabled(true);
                $role->setSystem(true);
                $changed = true;
            }

            $wantedKeys = $definition['permission_keys'];
            $currentKeys = [];
            foreach ($role->getPermissions() as $permission) {
                $currentKeys[] = $permission->getKey();
            }
            sort($currentKeys);
            $sortedWanted = $wantedKeys;
            sort($sortedWanted);

            if ($currentKeys !== $sortedWanted) {
                $role->clearPermissions();
                foreach ($wantedKeys as $key) {
                    $permission = $byKey[$key] ?? null;
                    if ($permission instanceof InstancePermission) {
                        $role->addPermission($permission);
                    }
                }
                $changed = true;
            }
        }

        return $changed;
    }

    private function removeLegacyOperatorRoles(): bool
    {
        $changed = false;

        foreach (InstanceRoleCatalog::legacyOperatorCodes() as $code) {
            $role = $this->roleRepository->findOneByCode($code);
            if (!$role instanceof InstanceRole) {
                continue;
            }

            foreach ($role->getUsers()->toArray() as $user) {
                if ($user instanceof User) {
                    $user->removeInstanceRole($role);
                }
            }
            $role->clearPermissions();
            $this->entityManager->remove($role);
            $changed = true;
        }

        return $changed;
    }
}
