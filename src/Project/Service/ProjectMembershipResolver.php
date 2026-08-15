<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Resolves effective project role from membership, groups, share grants, and ROLE_ADMIN.
 */
final readonly class ProjectMembershipResolver
{
    public const string VIEW_AS_MEMBER_SESSION_KEY = ProjectAccessService::VIEW_AS_MEMBER_SESSION_KEY;

    public function __construct(
        private ProjectMembershipRepository $membershipRepository,
        private ProjectGroupAccessRepository $groupAccessRepository,
        private ProjectShareGrantStore $shareGrantStore,
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

    /**
     * Highest effective role from direct membership, linked groups, and active share grants.
     *
     * Results are memoized on the current request so Twig helpers do not re-query in loops.
     */
    public function resolveAccess(Project $project, User $user): ?ProjectAccess
    {
        $request = $this->requestStack->getCurrentRequest();
        $cacheKey = $this->accessCacheKey($project, $user);
        if (null !== $cacheKey && $request instanceof Request && $request->attributes->has($cacheKey)) {
            /** @var ProjectAccess|null $cached */
            $cached = $request->attributes->get($cacheKey);

            return $cached;
        }

        $access = $this->computeAccess($project, $user);

        if (null !== $cacheKey && $request instanceof Request) {
            $request->attributes->set($cacheKey, $access);
        }

        return $access;
    }

    public function isViewAsMemberActive(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !$request->hasSession()) {
            return false;
        }

        return true === $request->getSession()->get(self::VIEW_AS_MEMBER_SESSION_KEY);
    }

    private function accessCacheKey(Project $project, User $user): ?string
    {
        $projectId = $project->getId();
        $userId = $user->getId();
        if (null === $projectId || null === $userId) {
            return null;
        }

        return '_beacon_project_access_'.$projectId.'_'.$userId;
    }

    private function computeAccess(Project $project, User $user): ?ProjectAccess
    {
        $direct = $this->getDirectMembership($project, $user);
        if ($direct instanceof ProjectMembership && !$direct->isActive()) {
            $direct = null;
        }
        $groupRole = $this->groupAccessRepository->findHighestGroupRoleForUser($project, $user);
        // Issue-scoped share grants do not unlock project-wide surfaces (list, analytics, …).
        $shareViewer = $this->shareGrantStore->hasProjectWideShareGrant($project);

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
