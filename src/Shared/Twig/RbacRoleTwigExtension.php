<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Rbac\RbacRoleTranslator;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig filters for translatable RBAC role labels (REQ-RBAC-008).
 */
final class RbacRoleTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly RbacRoleTranslator $rbacRoleTranslator,
    ) {
    }

    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('rbac_role_name', $this->rbacRoleTranslator->name(...)),
            new TwigFilter('rbac_role_description', $this->rbacRoleTranslator->description(...)),
        ];
    }
}
