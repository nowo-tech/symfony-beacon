<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Entity\ProjectShareLink;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Security\ProjectPermission;
use Deprecated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Enforces project access via direct membership, linked groups, and optional share-link grants.
 *
 * Instance ROLE_ADMIN receives effective owner access on every project
 * (for Administration and cross-project operator actions), unless the
 * `_beacon_view_as_member` session flag is set (then Member).
 */
final readonly class ProjectAccessService
{
    public const string VIEW_AS_MEMBER_SESSION_KEY = '_beacon_view_as_member';

    /** @var string Session map: project uuid => ['expires' => int, 'issue' => ?string, 'share' => ?string] */
    public const string SHARE_ACCESS_SESSION_KEY = '_beacon_share_access';

    public function __construct(
        private ProjectMembershipRepository $membershipRepository,
        private ProjectGroupAccessRepository $groupAccessRepository,
        private ProjectShareLinkRepository $shareLinkRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Direct user↔project membership only (not via groups).
     */
    public function getDirectMembership(Project $project, User $user): ?ProjectMembership
    {
        return $this->membershipRepository->findOneByProjectAndUser($project, $user);
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
        $direct = $this->getDirectMembership($project, $user);
        if ($direct instanceof ProjectMembership && !$direct->isActive()) {
            $direct = null;
        }
        $groupRole = $this->groupAccessRepository->findHighestGroupRoleForUser($project, $user);
        // Issue-scoped share grants do not unlock project-wide surfaces (list, analytics, …).
        $shareViewer = $this->hasProjectWideShareGrant($project);

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $role = $this->isViewAsMemberActive() ? ProjectRole::Member : ProjectRole::Owner;

            return new ProjectAccess(
                role: $role,
                directMembership: $direct,
                viaGroup: $groupRole instanceof ProjectRole,
            );
        }

        if (!$direct instanceof ProjectMembership && !$groupRole instanceof ProjectRole && !$shareViewer) {
            return null;
        }

        $role = $this->maxRole(
            $direct?->getRole(),
            $groupRole,
        );
        if ($shareViewer) {
            $role = $this->maxRole($role, ProjectRole::Viewer);
        }

        return new ProjectAccess(
            role: $role,
            directMembership: $direct,
            viaGroup: $groupRole instanceof ProjectRole,
        );
    }

    public function isViewAsMemberActive(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !$request->hasSession()) {
            return false;
        }

        return true === $request->getSession()->get(self::VIEW_AS_MEMBER_SESSION_KEY);
    }

    /**
     * Grant temporary viewer access from a share link (session-scoped).
     *
     * Stores the share-link UUID so later authorization can re-check revoke/expiry
     * without relying only on the copied session timestamp.
     */
    public function grantShareAccess(Project $project, ?string $issueUuid, int $expiresAtUnix, string $shareLinkUuid): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        /** @var array<string, array{expires: int, issue: ?string, share: ?string}> $grants */
        $grants = $session->get(self::SHARE_ACCESS_SESSION_KEY, []);
        $grants[$project->getUuid()] = [
            'expires' => $expiresAtUnix,
            'issue' => $issueUuid,
            'share' => $shareLinkUuid,
        ];
        $session->set(self::SHARE_ACCESS_SESSION_KEY, $grants);
    }

    public function hasActiveShareGrant(Project $project): bool
    {
        return null !== $this->getActiveShareEntry($project);
    }

    /** Project-wide share grant (no issue UUID restriction). */
    public function hasProjectWideShareGrant(Project $project): bool
    {
        $entry = $this->getActiveShareEntry($project);
        if (null === $entry) {
            return false;
        }

        $issue = $entry['issue'] ?? null;

        return null === $issue || '' === $issue;
    }

    /** Share grant covers this issue (project-wide or matching issue UUID). */
    public function hasShareGrantForIssue(Project $project, string $issueUuid): bool
    {
        $entry = $this->getActiveShareEntry($project);
        if (null === $entry) {
            return false;
        }

        $scoped = $entry['issue'] ?? null;
        if (null === $scoped || '' === $scoped) {
            return true;
        }

        return $scoped === $issueUuid;
    }

    /**
     * Read access for a single issue: membership / group / project-wide share, or matching issue-scoped share.
     *
     * @throws AccessDeniedHttpException
     */
    public function requireIssueRead(Project $project, User $user, string $issueUuid): ProjectAccess
    {
        $access = $this->resolveAccess($project, $user);
        if ($access instanceof ProjectAccess) {
            return $access;
        }

        if ($this->hasShareGrantForIssue($project, $issueUuid)) {
            return new ProjectAccess(
                role: ProjectRole::Viewer,
                viaGroup: false,
            );
        }

        throw new AccessDeniedHttpException('You do not have access to this project.');
    }

    /**
     * @return array{expires: int, issue: ?string, share: ?string}|null
     */
    private function getActiveShareEntry(Project $project): ?array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !$request->hasSession()) {
            return null;
        }

        /** @var array<string, array{expires?: int, issue?: ?string, share?: ?string}> $grants */
        $grants = $request->getSession()->get(self::SHARE_ACCESS_SESSION_KEY, []);
        $entry = $grants[$project->getUuid()] ?? null;
        if (!\is_array($entry)) {
            return null;
        }

        $clearGrant = static function () use ($request, &$grants, $project): void {
            unset($grants[$project->getUuid()]);
            $request->getSession()->set(self::SHARE_ACCESS_SESSION_KEY, $grants);
        };

        $expires = (int) ($entry['expires'] ?? 0);
        if ($expires < time()) {
            $clearGrant();

            return null;
        }

        $shareUuid = isset($entry['share']) && \is_string($entry['share']) && '' !== $entry['share']
            ? $entry['share']
            : null;
        // Legacy grants without a share UUID cannot be re-validated after revoke.
        if (null === $shareUuid) {
            $clearGrant();

            return null;
        }

        $link = $this->shareLinkRepository->findOneByUuid($shareUuid);
        // Do not use isUsable(): max-uses exhaustion must block new opens, not revoke
        // an already-granted session. Revoke and expiry are the session invalidators.
        if (
            !$link instanceof ProjectShareLink
            || $link->getProject()?->getId() !== $project->getId()
            || $link->isRevoked()
            || $link->isExpired()
        ) {
            $clearGrant();

            return null;
        }

        return [
            'expires' => $expires,
            'issue' => isset($entry['issue']) && \is_string($entry['issue']) && '' !== $entry['issue']
                ? $entry['issue']
                : null,
            'share' => $shareUuid,
        ];
    }

    /**
     * @throws AccessDeniedHttpException when the user has no project access
     */
    public function requireAccess(Project $project, User $user): ProjectAccess
    {
        $access = $this->resolveAccess($project, $user);
        if (!$access instanceof ProjectAccess) {
            throw new AccessDeniedHttpException('You do not have access to this project.');
        }

        return $access;
    }

    /**
     * @return ProjectAccess effective access (prefer requireAccess in new code)
     *
     * @throws AccessDeniedHttpException when the user has no project access
     */
    public function requireMembership(Project $project, User $user): ProjectAccess
    {
        return $this->requireAccess($project, $user);
    }

    /**
     * Requires effective access with at least the given role (viewer < member < admin < full = owner by rank).
     *
     * For primary ownership (transfer), use {@see requirePrimaryOwner()} — rank alone lets {@see ProjectRole::Full} through.
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
     * Instance ROLE_ADMIN resolves as Owner and passes.
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
     * Requires access that may open the project Settings surface (any manage/delete grant).
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

    /** Pick the higher of two roles (viewer < member < admin < owner). */
    private function maxRole(?ProjectRole $a, ?ProjectRole $b): ProjectRole
    {
        if (!$a instanceof ProjectRole) {
            return $b ?? ProjectRole::Viewer;
        }
        if (!$b instanceof ProjectRole) {
            return $a;
        }

        return $a->rank() >= $b->rank() ? $a : $b;
    }
}
