<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Exception\ProjectAccessException;
use App\Project\Repository\ProjectMembershipRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Shared authorization rules for direct membership and group-link mutations.
 */
final readonly class ProjectMembershipPolicy
{
    public function __construct(
        private ProjectMembershipRepository $membershipRepository,
        private UserGroupMembershipRepository $userGroupMembershipRepository,
        private ProjectAccessService $projectAccess,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * Roles the actor may assign to direct members.
     *
     * @return list<ProjectRole>
     */
    public function assignableRoles(User $actor, Project $project): array
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return [ProjectRole::Owner, ProjectRole::Full, ProjectRole::Admin, ProjectRole::Member, ProjectRole::Viewer];
        }

        $access = $this->projectAccess->resolveAccess($project, $actor);
        if (!$access instanceof ProjectAccess || !$access->canManageMembers()) {
            return [];
        }

        if (ProjectRole::Owner === $access->role) {
            return [ProjectRole::Owner, ProjectRole::Full, ProjectRole::Admin, ProjectRole::Member, ProjectRole::Viewer];
        }

        return [ProjectRole::Admin, ProjectRole::Member, ProjectRole::Viewer];
    }

    /**
     * Roles the actor may assign to linked groups (never owner or full).
     *
     * @return list<ProjectRole>
     */
    public function assignableGroupRoles(User $actor, Project $project): array
    {
        return array_values(array_filter(
            $this->assignableRoles($actor, $project),
            static fn (ProjectRole $role): bool => !\in_array($role, [ProjectRole::Owner, ProjectRole::Full], true),
        ));
    }

    /** @throws ProjectAccessException */
    public function assertActorCanManage(Project $project, User $actor): void
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $access = $this->projectAccess->resolveAccess($project, $actor);
        if (!$access instanceof ProjectAccess || !$access->canManageMembers()) {
            throw ProjectAccessException::of(ProjectAccessException::FORBIDDEN);
        }
    }

    /** @throws ProjectAccessException */
    public function assertActorCanTransferOwnership(Project $project, User $actor): void
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $access = $this->projectAccess->resolveAccess($project, $actor);
        if (!$access instanceof ProjectAccess || ProjectRole::Owner !== $access->role) {
            throw ProjectAccessException::of(ProjectAccessException::FORBIDDEN);
        }
    }

    /** @throws ProjectAccessException */
    public function assertAssignableRole(User $actor, Project $project, ProjectRole $role): void
    {
        if (!\in_array($role, $this->assignableRoles($actor, $project), true)) {
            throw ProjectAccessException::of(ProjectAccessException::INVALID_ROLE);
        }
    }

    /** @throws ProjectAccessException */
    public function assertAssignableGroupRole(User $actor, Project $project, ProjectRole $role): void
    {
        if (!\in_array($role, $this->assignableGroupRoles($actor, $project), true)) {
            throw ProjectAccessException::of(ProjectAccessException::INVALID_ROLE);
        }
    }

    /**
     * Instance ROLE_ADMIN or project owner may link any group.
     * Project admins may only link groups they belong to.
     *
     * @throws ProjectAccessException
     */
    public function assertActorCanLinkGroup(User $actor, UserGroup $group, Project $project): void
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $access = $this->projectAccess->resolveAccess($project, $actor);
        if ($access instanceof ProjectAccess && ProjectRole::Owner === $access->role) {
            return;
        }

        if ($this->userGroupMembershipRepository->findOneByGroupAndUser($group, $actor) instanceof UserGroupMembership) {
            return;
        }

        throw ProjectAccessException::of(ProjectAccessException::GROUP_LINK_FORBIDDEN);
    }

    /** @throws ProjectAccessException */
    public function assertSameProject(Project $project, ProjectMembership $target): void
    {
        if ($target->getProject()?->getId() !== $project->getId()) {
            throw ProjectAccessException::of(ProjectAccessException::WRONG_PROJECT);
        }
    }

    /**
     * Admins cannot mutate owner or full memberships (instance ROLE_ADMIN may).
     *
     * @throws ProjectAccessException
     */
    public function assertCanMutateTarget(User $actor, Project $project, ProjectMembership $target): void
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $access = $this->projectAccess->resolveAccess($project, $actor);
        if (!$access instanceof ProjectAccess) {
            throw ProjectAccessException::of(ProjectAccessException::FORBIDDEN);
        }

        if (ProjectRole::Admin === $access->role && \in_array($target->getRole(), [ProjectRole::Owner, ProjectRole::Full], true)) {
            throw ProjectAccessException::of(ProjectAccessException::CANNOT_MANAGE_OWNER);
        }
    }

    /** Count direct (not group-derived) **active** owner memberships on the project. */
    public function countDirectOwners(Project $project): int
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return 0;
        }

        return $this->membershipRepository->countOwnersByProjectIds([$projectId])[$projectId] ?? 0;
    }
}
