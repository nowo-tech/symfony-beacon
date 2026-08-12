<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Exception\ProjectAccessException;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds, updates, and removes direct project memberships and group access links.
 *
 * Every mutating method records a {@see UserActionType} via {@see UserActionRecorder}
 * and flushes the entity manager. Domain failures raise {@see ProjectAccessException}
 * (mapped to flash keys by the controller via {@see ProjectAccessFlashKeys}).
 */
final readonly class ProjectMembershipManager
{
    public function __construct(
        private UserRepository $userRepository,
        private ProjectMembershipRepository $membershipRepository,
        private ProjectGroupAccessRepository $groupAccessRepository,
        private UserGroupMembershipRepository $userGroupMembershipRepository,
        private ProjectAccessService $projectAccess,
        private UserActionRecorder $actionRecorder,
        private AuthorizationCheckerInterface $authorizationChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Add an existing enabled user by email as a direct project member.
     *
     * @throws ProjectAccessException
     */
    public function addByEmail(Project $project, User $actor, string $email, ProjectRole $role): ProjectMembership
    {
        $this->assertActorCanManage($project, $actor);
        $this->assertAssignableRole($actor, $project, $role);

        $user = $this->userRepository->findOneByEmail($email);
        if (!$user instanceof User) {
            throw ProjectAccessException::of(ProjectAccessException::USER_NOT_FOUND);
        }
        if (!$user->isEnabled()) {
            throw ProjectAccessException::of(ProjectAccessException::USER_DISABLED);
        }
        if ($this->membershipRepository->findOneByProjectAndUser($project, $user) instanceof ProjectMembership) {
            throw ProjectAccessException::of(ProjectAccessException::ALREADY_MEMBER);
        }

        $membership = new ProjectMembership();
        $membership->setUser($user);
        $membership->setRole($role);
        $project->addMembership($membership);
        $this->actionRecorder->record(
            UserActionType::ProjectMemberAdded,
            $actor,
            $user,
            [
                'project' => $project->getName(),
                'project_uuid' => $project->getUuid(),
                'role' => $role->value,
            ],
        );
        $this->entityManager->flush();

        return $membership;
    }

    /**
     * Change a direct member's role (cannot remove the last owner).
     *
     * @throws ProjectAccessException
     */
    public function changeRole(Project $project, User $actor, ProjectMembership $target, ProjectRole $role): void
    {
        $this->assertActorCanManage($project, $actor);
        $this->assertSameProject($project, $target);
        $this->assertCanMutateTarget($actor, $project, $target);
        $this->assertAssignableRole($actor, $project, $role);

        if (ProjectRole::Owner === $target->getRole() && ProjectRole::Owner !== $role && $this->countDirectOwners($project) <= 1) {
            throw ProjectAccessException::of(ProjectAccessException::LAST_OWNER);
        }

        $from = $target->getRole()->value;
        $target->setRole($role);
        $this->actionRecorder->record(
            UserActionType::ProjectMemberRoleChanged,
            $actor,
            $target->getUser(),
            [
                'project' => $project->getName(),
                'project_uuid' => $project->getUuid(),
                'from' => $from,
                'to' => $role->value,
            ],
        );
        $this->entityManager->flush();
    }

    /**
     * Remove a direct membership (cannot remove the last owner).
     *
     * @throws ProjectAccessException
     */
    public function remove(Project $project, User $actor, ProjectMembership $target): void
    {
        $this->assertActorCanManage($project, $actor);
        $this->assertSameProject($project, $target);
        $this->assertCanMutateTarget($actor, $project, $target);

        if (ProjectRole::Owner === $target->getRole() && $this->countDirectOwners($project) <= 1) {
            throw ProjectAccessException::of(ProjectAccessException::LAST_OWNER);
        }
        if (ProjectRole::Full === $target->getRole()) {
            throw ProjectAccessException::of(ProjectAccessException::CANNOT_REMOVE_FULL);
        }

        $subject = $target->getUser();
        $removedRole = $target->getRole()->value;
        $project->removeMembership($target);
        $this->entityManager->remove($target);
        $this->actionRecorder->record(
            UserActionType::ProjectMemberRemoved,
            $actor,
            $subject,
            [
                'project' => $project->getName(),
                'project_uuid' => $project->getUuid(),
                'role' => $removedRole,
            ],
        );
        $this->entityManager->flush();
    }

    /**
     * Transfer project ownership to another direct member.
     *
     * Promotes the target to owner. If the actor has a direct owner membership,
     * demotes the actor to {@see ProjectRole::Full} so they keep the full permission
     * matrix without remaining the primary owner.
     *
     * @throws ProjectAccessException
     */
    public function transferOwnership(Project $project, User $actor, ProjectMembership $target): void
    {
        $this->assertActorCanTransferOwnership($project, $actor);
        $this->assertSameProject($project, $target);

        $newOwner = $target->getUser();
        if ($newOwner->getId() === $actor->getId()) {
            throw ProjectAccessException::of(ProjectAccessException::CANNOT_TRANSFER_TO_SELF);
        }
        if (!$newOwner->isEnabled()) {
            throw ProjectAccessException::of(ProjectAccessException::USER_DISABLED);
        }

        $actorMembership = $this->membershipRepository->findOneByProjectAndUser($project, $actor);
        $actorWillDemote = $actorMembership instanceof ProjectMembership
            && ProjectRole::Owner === $actorMembership->getRole();

        if (ProjectRole::Owner === $target->getRole() && !$actorWillDemote) {
            throw ProjectAccessException::of(ProjectAccessException::ALREADY_OWNER);
        }

        $fromRole = $target->getRole()->value;
        $target->setRole(ProjectRole::Owner);

        $actorFormerRole = null;
        if ($actorWillDemote && $actorMembership instanceof ProjectMembership) {
            $actorFormerRole = $actorMembership->getRole()->value;
            $actorMembership->setRole(ProjectRole::Full);
        }

        $this->actionRecorder->record(
            UserActionType::ProjectOwnershipTransferred,
            $actor,
            $newOwner,
            [
                'project' => $project->getName(),
                'project_uuid' => $project->getUuid(),
                'from' => $fromRole,
                'to' => ProjectRole::Owner->value,
                'actor_former_role' => $actorFormerRole,
                'actor_new_role' => $actorWillDemote ? ProjectRole::Full->value : null,
            ],
        );
        $this->entityManager->flush();
    }

    /**
     * Link a user group to the project (admin/member only).
     *
     * @throws ProjectAccessException
     */
    public function addGroup(Project $project, User $actor, UserGroup $group, ProjectRole $role): ProjectGroupAccess
    {
        $this->assertActorCanManage($project, $actor);
        $this->assertActorCanLinkGroup($actor, $group, $project);
        if (\in_array($role, [ProjectRole::Owner, ProjectRole::Full], true)) {
            throw ProjectAccessException::of(ProjectAccessException::INVALID_ROLE);
        }
        $this->assertAssignableGroupRole($actor, $project, $role);

        if ($this->groupAccessRepository->findOneByProjectAndGroup($project, $group) instanceof ProjectGroupAccess) {
            throw ProjectAccessException::of(ProjectAccessException::GROUP_ALREADY_LINKED);
        }

        $access = new ProjectGroupAccess();
        $access->setUserGroup($group);
        $access->setRole($role);
        $project->addGroupAccess($access);
        $this->actionRecorder->record(
            UserActionType::ProjectGroupLinked,
            $actor,
            null,
            [
                'project' => $project->getName(),
                'project_uuid' => $project->getUuid(),
                'group' => $group->getName(),
                'group_uuid' => $group->getUuid(),
                'role' => $role->value,
            ],
        );
        $this->entityManager->flush();

        return $access;
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

    /**
     * Change the role of a linked group (admin/member only).
     *
     * @throws ProjectAccessException
     */
    public function changeGroupRole(Project $project, User $actor, ProjectGroupAccess $target, ProjectRole $role): void
    {
        $this->assertActorCanManage($project, $actor);
        if ($target->getProject()?->getId() !== $project->getId()) {
            throw ProjectAccessException::of(ProjectAccessException::WRONG_PROJECT);
        }
        if (\in_array($role, [ProjectRole::Owner, ProjectRole::Full], true)) {
            throw ProjectAccessException::of(ProjectAccessException::INVALID_ROLE);
        }
        $this->assertAssignableGroupRole($actor, $project, $role);

        $from = $target->getRole()->value;
        $target->setRole($role);
        $group = $target->getUserGroup();
        $this->actionRecorder->record(
            UserActionType::ProjectGroupRoleChanged,
            $actor,
            null,
            [
                'project' => $project->getName(),
                'project_uuid' => $project->getUuid(),
                'group' => $group?->getName(),
                'group_uuid' => $group?->getUuid(),
                'from' => $from,
                'to' => $role->value,
            ],
        );
        $this->entityManager->flush();
    }

    /**
     * Unlink a group from the project.
     *
     * @throws ProjectAccessException
     */
    public function removeGroup(Project $project, User $actor, ProjectGroupAccess $target): void
    {
        $this->assertActorCanManage($project, $actor);
        if ($target->getProject()?->getId() !== $project->getId()) {
            throw ProjectAccessException::of(ProjectAccessException::WRONG_PROJECT);
        }

        $group = $target->getUserGroup();
        $removedRole = $target->getRole()->value;
        $project->removeGroupAccess($target);
        $this->entityManager->remove($target);
        $this->actionRecorder->record(
            UserActionType::ProjectGroupUnlinked,
            $actor,
            null,
            [
                'project' => $project->getName(),
                'project_uuid' => $project->getUuid(),
                'group' => $group?->getName(),
                'group_uuid' => $group?->getUuid(),
                'role' => $removedRole,
            ],
        );
        $this->entityManager->flush();
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
    private function assertActorCanManage(Project $project, User $actor): void
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
    private function assertActorCanTransferOwnership(Project $project, User $actor): void
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
    private function assertAssignableRole(User $actor, Project $project, ProjectRole $role): void
    {
        if (!\in_array($role, $this->assignableRoles($actor, $project), true)) {
            throw ProjectAccessException::of(ProjectAccessException::INVALID_ROLE);
        }
    }

    /** @throws ProjectAccessException */
    private function assertAssignableGroupRole(User $actor, Project $project, ProjectRole $role): void
    {
        if (!\in_array($role, $this->assignableGroupRoles($actor, $project), true)) {
            throw ProjectAccessException::of(ProjectAccessException::INVALID_ROLE);
        }
    }

    /** @throws ProjectAccessException */
    private function assertSameProject(Project $project, ProjectMembership $target): void
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
    private function assertCanMutateTarget(User $actor, Project $project, ProjectMembership $target): void
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
    private function countDirectOwners(Project $project): int
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return 0;
        }

        return $this->membershipRepository->countOwnersByProjectIds([$projectId])[$projectId] ?? 0;
    }

    /**
     * Activate or deactivate a direct membership (does not delete the row).
     *
     * @throws ProjectAccessException
     */
    public function setActive(Project $project, User $actor, ProjectMembership $target, bool $active): void
    {
        $this->assertActorCanManage($project, $actor);
        $this->assertCanMutateTarget($actor, $project, $target);

        if ($target->getProject()?->getId() !== $project->getId()) {
            throw ProjectAccessException::of(ProjectAccessException::FORBIDDEN);
        }

        if (!$active && ProjectRole::Owner === $target->getRole() && $this->countDirectOwners($project) <= 1) {
            throw ProjectAccessException::of(ProjectAccessException::LAST_OWNER);
        }

        if ($target->isActive() === $active) {
            return;
        }

        $target->setActive($active);
        $user = $target->getUser();
        $this->actionRecorder->record(
            $active ? UserActionType::ProjectMemberActivated : UserActionType::ProjectMemberDeactivated,
            $actor,
            $user,
            [
                'project' => $project->getName(),
                'project_uuid' => $project->getUuid(),
                'role' => $target->getRole()->value,
                'active' => $active ? 1 : 0,
            ],
        );
        $this->entityManager->flush();
    }
}
