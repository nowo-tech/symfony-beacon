<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\InstancePermission;
use App\Identity\Repository\InstancePermissionRepository;

/**
 * Groups instance permissions by category for Administration → Roles matrix UI.
 */
final readonly class InstancePermissionMatrixBuilder
{
    public function __construct(
        private InstancePermissionRepository $permissionRepository,
    ) {
    }

    /**
     * @return array<string, list<InstancePermission>>
     */
    public function permissionsByCategory(): array
    {
        $permissionsByCategory = [];
        foreach ($this->permissionRepository->findAllOrdered() as $permission) {
            $permissionsByCategory[$permission->getCategory()][] = $permission;
        }

        return $permissionsByCategory;
    }
}
