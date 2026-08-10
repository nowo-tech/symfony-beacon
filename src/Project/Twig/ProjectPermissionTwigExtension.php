<?php

declare(strict_types=1);

namespace App\Project\Twig;

use App\Identity\Entity\User;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers for per-project {@see \App\Project\Security\ProjectPermission} checks.
 *
 * Controllers must still enforce access with {@see ProjectAccessService::requirePermission()}
 * (or related helpers) and return 403. These functions only hide UI; they are not a security boundary.
 *
 * Usage:
 * - `project_grants(project, 'project.settings.manage')`
 * - `project_access(project)` → {@see ProjectAccess}|null
 * - `project_can_open_settings(project)` → Settings tab / surface
 *
 * @see docs/product/ROLES.md
 */
final class ProjectPermissionTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProjectAccessService $projectAccess,
        private readonly Security $security,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('project_grants', $this->grants(...)),
            new TwigFunction('project_access', $this->access(...)),
            new TwigFunction('project_can_open_settings', $this->canOpenSettings(...)),
        ];
    }

    /**
     * Whether the current user grants a project permission key on this project.
     *
     * Unknown or empty keys return false. Unauthenticated users return false.
     */
    public function grants(Project $project, string $permission): bool
    {
        $access = $this->access($project);
        if (!$access instanceof ProjectAccess) {
            return false;
        }

        $permission = strtolower(trim($permission));
        if ('' === $permission) {
            return false;
        }

        return $access->grants($permission);
    }

    /**
     * Effective {@see ProjectAccess} for the current user, or null when there is no project access.
     */
    public function access(Project $project): ?ProjectAccess
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->projectAccess->resolveAccess($project, $user);
    }

    /**
     * Whether the Settings tab /settings URL should be offered (any manage/delete grant).
     */
    public function canOpenSettings(Project $project): bool
    {
        $access = $this->access($project);

        return $access instanceof ProjectAccess && $access->canOpenSettings();
    }
}
