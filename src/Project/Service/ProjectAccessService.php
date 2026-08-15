<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use Deprecated;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Façade for project access: membership resolve, share grants, and require* guards.
 *
 * Prefer injecting {@see ProjectMembershipResolver}, {@see ProjectShareGrantStore}, or
 * {@see ProjectAccessGuard} in new code when only one concern is needed.
 */
final readonly class ProjectAccessService
{
    public const string VIEW_AS_MEMBER_SESSION_KEY = '_beacon_view_as_member';

    /** @var string Session map: project uuid => ['expires' => int, 'issue' => ?string, 'share' => ?string] */
    public const string SHARE_ACCESS_SESSION_KEY = '_beacon_share_access';

    public function __construct(
        private ProjectMembershipResolver $membershipResolver,
        private ProjectShareGrantStore $shareGrantStore,
        private ProjectAccessGuard $accessGuard,
    ) {
    }

    /**
     * Direct user↔project membership only (not via groups).
     */
    public function getDirectMembership(Project $project, User $user): ?ProjectMembership
    {
        return $this->membershipResolver->getDirectMembership($project, $user);
    }

    #[Deprecated(message: 'Use getDirectMembership(); kept for callers that mean direct rows')]
    public function getMembership(Project $project, User $user): ?ProjectMembership
    {
        return $this->getDirectMembership($project, $user);
    }

    /**
     * Highest effective role from direct membership, linked groups, and active share grants.
     * Instance ROLE_ADMIN always resolves as owner (even without membership),
     * unless view-as-member is active (then Member).
     */
    public function resolveAccess(Project $project, User $user): ?ProjectAccess
    {
        return $this->membershipResolver->resolveAccess($project, $user);
    }

    public function isViewAsMemberActive(): bool
    {
        return $this->membershipResolver->isViewAsMemberActive();
    }

    /**
     * Grant temporary viewer access from a share link (session-scoped).
     */
    public function grantShareAccess(Project $project, ?string $issueUuid, int $expiresAtUnix, string $shareLinkUuid): void
    {
        $this->shareGrantStore->grantShareAccess($project, $issueUuid, $expiresAtUnix, $shareLinkUuid);
    }

    public function hasActiveShareGrant(Project $project): bool
    {
        return $this->shareGrantStore->hasActiveShareGrant($project);
    }

    /** Project-wide share grant (no issue UUID restriction). */
    public function hasProjectWideShareGrant(Project $project): bool
    {
        return $this->shareGrantStore->hasProjectWideShareGrant($project);
    }

    /** Share grant covers this issue (project-wide or matching issue UUID). */
    public function hasShareGrantForIssue(Project $project, string $issueUuid): bool
    {
        return $this->shareGrantStore->hasShareGrantForIssue($project, $issueUuid);
    }

    /**
     * @throws AccessDeniedHttpException
     */
    public function requireIssueRead(Project $project, User $user, string $issueUuid): ProjectAccess
    {
        return $this->accessGuard->requireIssueRead($project, $user, $issueUuid);
    }

    /**
     * @throws AccessDeniedHttpException when the user has no project access
     */
    public function requireAccess(Project $project, User $user): ProjectAccess
    {
        return $this->accessGuard->requireAccess($project, $user);
    }

    /**
     * @throws AccessDeniedHttpException when the user has no project access
     */
    public function requireMembership(Project $project, User $user): ProjectAccess
    {
        return $this->accessGuard->requireMembership($project, $user);
    }

    /**
     * @throws AccessDeniedHttpException when access or role is insufficient
     */
    public function requireRole(Project $project, User $user, ProjectRole $minimum): ProjectAccess
    {
        return $this->accessGuard->requireRole($project, $user, $minimum);
    }

    /**
     * @throws AccessDeniedHttpException
     */
    public function requirePrimaryOwner(Project $project, User $user): ProjectAccess
    {
        return $this->accessGuard->requirePrimaryOwner($project, $user);
    }

    /**
     * @throws AccessDeniedHttpException
     */
    public function requireTriage(Project $project, User $user): ProjectAccess
    {
        return $this->accessGuard->requireTriage($project, $user);
    }

    /**
     * @throws AccessDeniedHttpException when access or permission is insufficient
     */
    public function requirePermission(Project $project, User $user, string $permission): ProjectAccess
    {
        return $this->accessGuard->requirePermission($project, $user, $permission);
    }

    /**
     * @throws AccessDeniedHttpException when access or permission is insufficient
     */
    public function requireAnyPermission(Project $project, User $user, string ...$permissions): ProjectAccess
    {
        return $this->accessGuard->requireAnyPermission($project, $user, ...$permissions);
    }

    /**
     * @throws AccessDeniedHttpException
     */
    public function requireSettingsSurface(Project $project, User $user): ProjectAccess
    {
        return $this->accessGuard->requireSettingsSurface($project, $user);
    }
}
