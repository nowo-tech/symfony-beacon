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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Enforces project access via direct membership and/or linked user groups.
 *
 * Instance ROLE_ADMIN receives effective owner access on every project
 * (for Administration and cross-project operator actions).
 */
final readonly class ProjectAccessService
{
    private const ROLE_RANK = [
        'member' => 1,
        'admin' => 2,
        'owner' => 3,
    ];

    public function __construct(
        private ProjectMembershipRepository $membershipRepository,
        private ProjectGroupAccessRepository $groupAccessRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * Direct user↔project membership only (not via groups).
     */
    public function getDirectMembership(Project $project, User $user): ?ProjectMembership
    {
        return $this->membershipRepository->findOneByProjectAndUser($project, $user);
    }

    /**
     * @deprecated Use getDirectMembership(); kept for callers that mean direct rows
     */
    public function getMembership(Project $project, User $user): ?ProjectMembership
    {
        return $this->getDirectMembership($project, $user);
    }

    /**
     * Highest effective role from direct membership and linked groups, or null if none.
     * Instance ROLE_ADMIN always resolves as owner (even without membership).
     */
    public function resolveAccess(Project $project, User $user): ?ProjectAccess
    {
        $direct = $this->getDirectMembership($project, $user);
        $groupRole = $this->groupAccessRepository->findHighestGroupRoleForUser($project, $user);

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return new ProjectAccess(
                role: ProjectRole::Owner,
                directMembership: $direct,
                viaGroup: null !== $groupRole,
            );
        }

        if (!$direct instanceof ProjectMembership && null === $groupRole) {
            return null;
        }

        $role = $this->maxRole(
            $direct?->getRole(),
            $groupRole,
        );

        return new ProjectAccess(
            role: $role,
            directMembership: $direct,
            viaGroup: null !== $groupRole,
        );
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
     * Requires effective access with at least the given role (member < admin < owner).
     *
     * @throws AccessDeniedHttpException when access or role is insufficient
     */
    public function requireRole(Project $project, User $user, ProjectRole $minimum): ProjectAccess
    {
        $access = $this->requireAccess($project, $user);
        if (self::ROLE_RANK[$access->role->value] < self::ROLE_RANK[$minimum->value]) {
            throw new AccessDeniedHttpException('Insufficient project permissions.');
        }

        return $access;
    }

    /** Pick the higher of two roles (member < admin < owner). */
    private function maxRole(?ProjectRole $a, ?ProjectRole $b): ProjectRole
    {
        if (!$a instanceof ProjectRole) {
            return $b ?? ProjectRole::Member;
        }
        if (!$b instanceof ProjectRole) {
            return $a;
        }

        return self::ROLE_RANK[$a->value] >= self::ROLE_RANK[$b->value] ? $a : $b;
    }
}
