<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Rbac\RbacPermissionCategoryTranslator;
use App\Shared\Rbac\RbacPermissionTranslator;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig filters for translatable RBAC permission / category labels (REQ-RBAC-008).
 */
final class RbacPermissionTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly RbacPermissionTranslator $rbacPermissionTranslator,
        private readonly RbacPermissionCategoryTranslator $rbacPermissionCategoryTranslator,
    ) {
    }

    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('rbac_permission_name', $this->rbacPermissionTranslator->name(...)),
            new TwigFilter('rbac_permission_description', $this->rbacPermissionTranslator->description(...)),
            new TwigFilter('rbac_permission_category_name', $this->rbacPermissionCategoryTranslator->name(...)),
            new TwigFilter('rbac_permission_category_description', $this->rbacPermissionCategoryTranslator->description(...)),
        ];
    }
}
