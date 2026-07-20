<?php

declare(strict_types=1);

namespace App\Shared\Menu;

use Nowo\DashboardMenuBundle\Attribute\PermissionCheckerLabel;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Shows menu items when the user is granted at least one permission key via Security::isGranted().
 * Items without keys remain visible.
 */
#[PermissionCheckerLabel('Symfony Security isGranted')]
final class SecurityIsGrantedMenuPermissionChecker implements MenuPermissionCheckerInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function canView(MenuItem $item, mixed $context = null): bool
    {
        $keys = $item->getPermissionKeys() ?? [];
        if ([] === $keys) {
            return true;
        }

        foreach ($keys as $key) {
            if ($this->security->isGranted($key)) {
                return true;
            }
        }

        return false;
    }
}
