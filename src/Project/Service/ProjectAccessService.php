<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Shared\ProjectRole;
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

    /** @var string Session map: project uuid => ['expires' => int, 'issue' => ?string] */
    public const string SHARE_ACCESS_SESSION_KEY = '_beacon_share_access';

    public function __construct(
        private ProjectMembershipRepository $membershipRepository,
        private ProjectGroupAccessRepository $groupAccessRepository,
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
        $groupRole = $this->groupAccessRepository->findHighestGroupRoleForUser($project, $user);
        $shareViewer = $this->hasActiveShareGrant($project);

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
     */
    public function grantShareAccess(Project $project, ?string $issueUuid, int $expiresAtUnix): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        /** @var array<string, array{expires: int, issue: ?string}> $grants */
        $grants = $session->get(self::SHARE_ACCESS_SESSION_KEY, []);
        $grants[$project->getUuid()] = [
            'expires' => $expiresAtUnix,
            'issue' => $issueUuid,
        ];
        $session->set(self::SHARE_ACCESS_SESSION_KEY, $grants);
    }

    public function hasActiveShareGrant(Project $project): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !$request->hasSession()) {
            return false;
        }

        /** @var array<string, array{expires?: int, issue?: ?string}> $grants */
        $grants = $request->getSession()->get(self::SHARE_ACCESS_SESSION_KEY, []);
        $entry = $grants[$project->getUuid()] ?? null;
        if (!\is_array($entry)) {
            return false;
        }

        $expires = (int) ($entry['expires'] ?? 0);
        if ($expires < time()) {
            unset($grants[$project->getUuid()]);
            $request->getSession()->set(self::SHARE_ACCESS_SESSION_KEY, $grants);

            return false;
        }

        return true;
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
     * Requires effective access with at least the given role (viewer < member < admin < owner).
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
     * Requires membership that may triage issues (member+).
     *
     * @throws AccessDeniedHttpException
     */
    public function requireTriage(Project $project, User $user): ProjectAccess
    {
        $access = $this->requireAccess($project, $user);
        if (!$access->canTriageIssues()) {
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
