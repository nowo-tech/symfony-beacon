<?php

declare(strict_types=1);

namespace App\Identity\Tour;

use App\Shared\ProjectRole;

/**
 * Runtime context used to filter tour steps by page, instance role, and project permissions.
 */
final readonly class ProductTourContext
{
    public function __construct(
        public ProductTourPage $page,
        public bool $isInstanceAdmin,
        public bool $canCreateProject,
        public ?ProjectRole $projectRole = null,
    ) {
    }

    public function canTriageIssues(): bool
    {
        return $this->projectRole?->canTriageIssues() ?? false;
    }

    public function canManageProject(): bool
    {
        return $this->projectRole?->canManageApiKeys() ?? false;
    }
}
