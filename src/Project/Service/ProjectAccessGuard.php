<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectRole;
use App\Project\Security\ProjectPermission;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Throws {@see AccessDeniedHttpException} when effective project access is insufficient.
 */
final readonly class ProjectAccessGuard
{
    public function __construct(
        private ProjectMembershipResolver $membershipResolver,
        private ProjectShareGrantStore $shareGrantStore,
    ) {
    }

    /**
     * Read access for a single issue: membership / group / project-wide share, or matching issue-scoped share.
     *
     * @throws AccessDeniedHttpException
     */
    public function requireIssueRead(Project $project, User $user, string $issueUuid): ProjectAccess
    {
        $access = $this->membershipResolver->resolveAccess($project, $user);
        if ($access instanceof ProjectAccess) {
            return $access;
        }

        if ($this->shareGrantStore->hasShareGrantForIssue($project, $issueUuid)) {
            return new ProjectAccess(
                role: ProjectRole::Viewer,
                viaGroup: false,
            );
        }

        throw new AccessDeniedHttpException('You do not have access to this project.');
    }

    /**
     * @throws AccessDeniedHttpException when the user has no project access
     */
    public function requireAccess(Project $project, User $user): ProjectAccess
    {
        $access = $this->membershipResolver->resolveAccess($project, $user);
        if (!$access instanceof ProjectAccess) {
            throw new AccessDeniedHttpException('You do not have access to this project.');
        }

        return $access;
    }

    /**
     * @throws AccessDeniedHttpException when the user has no project access
     */
    public function requireMembership(Project $project, User $user): ProjectAccess
    {
        return $this->requireAccess($project, $user);
    }

    /**
     * Requires effective access with at least the given role.
     *
     * @throws AccessDeniedHttpException when access or role is insufficient
     */
    public function requireRole(Project $project, User $user, ProjectRole $minimum): ProjectAccess
    {
        $access = $this->requireAccess($project, $user);
        if ($access->role->rank() < $minimum->rank()) {
            throw new AccessDeniedHttpException('Insufficient project permissions.');
        }

        return $access;
    }

    /**
     * Requires primary project owner ({@see ProjectRole::Owner} exactly), not {@see ProjectRole::Full}.
     *
     * @throws AccessDeniedHttpException
     */
    public function requirePrimaryOwner(Project $project, User $user): ProjectAccess
    {
        $access = $this->requireAccess($project, $user);
        if (ProjectRole::Owner !== $access->role) {
            throw new AccessDeniedHttpException('Insufficient project permissions.');
        }

        return $access;
    }

    /**
     * Requires membership that may triage issues (member+).
     *
     * @throws AccessDeniedHttpException
     */
    public function requireTriage(Project $project, User $user): ProjectAccess
    {
        return $this->requirePermission($project, $user, ProjectPermission::ISSUES_TRIAGE);
    }

    /**
     * Requires effective access that grants a {@see ProjectPermission} key.
     *
     * @throws AccessDeniedHttpException when access or permission is insufficient
     */
    public function requirePermission(Project $project, User $user, string $permission): ProjectAccess
    {
        $access = $this->requireAccess($project, $user);
        if (!$access->grants($permission)) {
            throw new AccessDeniedHttpException('Insufficient project permissions.');
        }

        return $access;
    }

    /**
     * Requires effective access that grants at least one of the given permission keys.
     *
     * @throws AccessDeniedHttpException when access or permission is insufficient
     */
    public function requireAnyPermission(Project $project, User $user, string ...$permissions): ProjectAccess
    {
        $access = $this->requireAccess($project, $user);
        if ([] === $permissions || !$access->grantsAny(...$permissions)) {
            throw new AccessDeniedHttpException('Insufficient project permissions.');
        }

        return $access;
    }

    /**
     * Requires access that may open the project Settings surface.
     *
     * @throws AccessDeniedHttpException
     */
    public function requireSettingsSurface(Project $project, User $user): ProjectAccess
    {
        $access = $this->requireAccess($project, $user);
        if (!$access->canOpenSettings()) {
            throw new AccessDeniedHttpException('Insufficient project permissions.');
        }

        return $access;
    }
}
