<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Shared\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds, updates, and removes direct project memberships and group access links.
 *
 * Every mutating method records a {@see UserActionType} via {@see UserActionRecorder}
 * and flushes the entity manager. Domain failure codes are thrown as
 * {@see InvalidArgumentException} or {@see RuntimeException} message strings
 * (mapped to flash keys by the controller).
 */
final readonly class ProjectMembershipManager
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ProjectMembershipRepository $membershipRepository,
        private readonly ProjectGroupAccessRepository $groupAccessRepository,
        private readonly UserGroupMembershipRepository $userGroupMembershipRepository,
        private readonly ProjectAccessService $projectAccess,
        private readonly UserActionRecorder $actionRecorder,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Add an existing enabled user by email as a direct project member.
     *
     * @throws InvalidArgumentException when the email/role cannot be applied
     * @throws RuntimeException         when domain rules block the change
     */
    public function addByEmail(Project $project, User $actor, string $email, ProjectRole $role): ProjectMembership
    {
        $this->assertActorCanManage($project, $actor);
        $this->assertAssignableRole($actor, $project, $role);

        $user = $this->userRepository->findOneByEmail($email);
        if (!$user instanceof User) {
            throw new InvalidArgumentException('user_not_found');
        }
        if (!$user->isEnabled()) {
            throw new InvalidArgumentException('user_disabled');
        }
        if ($this->membershipRepository->findOneByProjectAndUser($project, $user) instanceof ProjectMembership) {
            throw new InvalidArgumentException('already_member');
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
     * @throws InvalidArgumentException when the role is invalid for the actor
     * @throws RuntimeException         when domain rules block the change
     */
    public function changeRole(Project $project, User $actor, ProjectMembership $target, ProjectRole $role): void
    {
        $this->assertActorCanManage($project, $actor);
        $this->assertSameProject($project, $target);
        $this->assertCanMutateTarget($actor, $project, $target);
        $this->assertAssignableRole($actor, $project, $role);

        if (ProjectRole::Owner === $target->getRole() && ProjectRole::Owner !== $role && $this->countDirectOwners($project) <= 1) {
            throw new RuntimeException('last_owner');
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
     * @throws RuntimeException when domain rules block the removal
     */
    public function remove(Project $project, User $actor, ProjectMembership $target): void
    {
        $this->assertActorCanManage($project, $actor);
        $this->assertSameProject($project, $target);
        $this->assertCanMutateTarget($actor, $project, $target);

        if (ProjectRole::Owner === $target->getRole() && $this->countDirectOwners($project) <= 1) {
            throw new RuntimeException('last_owner');
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
     * demotes the actor to admin so ownership is handed off atomically.
     *
     * @throws InvalidArgumentException when the target cannot receive ownership
     * @throws RuntimeException         when the actor cannot transfer ownership
     */
    public function transferOwnership(Project $project, User $actor, ProjectMembership $target): void
    {
        $this->assertActorCanTransferOwnership($project, $actor);
        $this->assertSameProject($project, $target);

        $newOwner = $target->getUser();
        if ($newOwner->getId() === $actor->getId()) {
            throw new InvalidArgumentException('cannot_transfer_to_self');
        }
        if (!$newOwner->isEnabled()) {
            throw new InvalidArgumentException('user_disabled');
        }

        $actorMembership = $this->membershipRepository->findOneByProjectAndUser($project, $actor);
        $actorWillDemote = $actorMembership instanceof ProjectMembership
            && ProjectRole::Owner === $actorMembership->getRole();

        if (ProjectRole::Owner === $target->getRole() && !$actorWillDemote) {
            throw new InvalidArgumentException('already_owner');
        }

        $fromRole = $target->getRole()->value;
        $target->setRole(ProjectRole::Owner);

        $actorFormerRole = null;
        if ($actorWillDemote && $actorMembership instanceof ProjectMembership) {
            $actorFormerRole = $actorMembership->getRole()->value;
            $actorMembership->setRole(ProjectRole::Admin);
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
                'actor_new_role' => $actorWillDemote ? ProjectRole::Admin->value : null,
            ],
        );
        $this->entityManager->flush();
    }

    /**
     * Link a user group to the project (admin/member only).
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function addGroup(Project $project, User $actor, UserGroup $group, ProjectRole $role): ProjectGroupAccess
    {
        $this->assertActorCanManage($project, $actor);
        $this->assertActorCanLinkGroup($actor, $group, $project);
        if (ProjectRole::Owner === $role) {
            throw new InvalidArgumentException('invalid_role');
        }
        $this->assertAssignableGroupRole($actor, $project, $role);

        if ($this->groupAccessRepository->findOneByProjectAndGroup($project, $group) instanceof ProjectGroupAccess) {
            throw new InvalidArgumentException('group_already_linked');
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
     * @throws RuntimeException when the actor cannot link this group
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

        if (null !== $this->userGroupMembershipRepository->findOneByGroupAndUser($group, $actor)) {
            return;
        }

        throw new RuntimeException('group_link_forbidden');
    }

    /**
     * Change the role of a linked group (admin/member only).
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function changeGroupRole(Project $project, User $actor, ProjectGroupAccess $target, ProjectRole $role): void
    {
        $this->assertActorCanManage($project, $actor);
        if ($target->getProject()?->getId() !== $project->getId()) {
            throw new InvalidArgumentException('wrong_project');
        }
        if (ProjectRole::Owner === $role) {
            throw new InvalidArgumentException('invalid_role');
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
     * @throws InvalidArgumentException|RuntimeException
     */
    public function removeGroup(Project $project, User $actor, ProjectGroupAccess $target): void
    {
        $this->assertActorCanManage($project, $actor);
        if ($target->getProject()?->getId() !== $project->getId()) {
            throw new InvalidArgumentException('wrong_project');
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
        $access = $this->projectAccess->resolveAccess($project, $actor);
        if (!$access instanceof ProjectAccess || !$access->canManageMembers()) {
            return [];
        }

        if (ProjectRole::Owner === $access->role) {
            return [ProjectRole::Owner, ProjectRole::Admin, ProjectRole::Member];
        }

        return [ProjectRole::Admin, ProjectRole::Member];
    }

    /**
     * Roles the actor may assign to linked groups (never owner).
     *
     * @return list<ProjectRole>
     */
    public function assignableGroupRoles(User $actor, Project $project): array
    {
        return array_values(array_filter(
            $this->assignableRoles($actor, $project),
            static fn (ProjectRole $role): bool => ProjectRole::Owner !== $role,
        ));
    }

    /** @throws RuntimeException when the actor cannot manage members */
    private function assertActorCanManage(Project $project, User $actor): void
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $access = $this->projectAccess->resolveAccess($project, $actor);
        if (!$access instanceof ProjectAccess || !$access->canManageMembers()) {
            throw new RuntimeException('forbidden');
        }
    }

    /** @throws RuntimeException when the actor cannot transfer project ownership */
    private function assertActorCanTransferOwnership(Project $project, User $actor): void
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $access = $this->projectAccess->resolveAccess($project, $actor);
        if (!$access instanceof ProjectAccess || ProjectRole::Owner !== $access->role) {
            throw new RuntimeException('forbidden');
        }
    }

    /** @throws InvalidArgumentException when the role is not assignable by this actor */
    private function assertAssignableRole(User $actor, Project $project, ProjectRole $role): void
    {
        if (!\in_array($role, $this->assignableRoles($actor, $project), true)) {
            throw new InvalidArgumentException('invalid_role');
        }
    }

    /** @throws InvalidArgumentException when the group role is not assignable by this actor */
    private function assertAssignableGroupRole(User $actor, Project $project, ProjectRole $role): void
    {
        if (!\in_array($role, $this->assignableGroupRoles($actor, $project), true)) {
            throw new InvalidArgumentException('invalid_role');
        }
    }

    /** @throws InvalidArgumentException when the membership belongs to another project */
    private function assertSameProject(Project $project, ProjectMembership $target): void
    {
        if ($target->getProject()?->getId() !== $project->getId()) {
            throw new InvalidArgumentException('wrong_project');
        }
    }

    /**
     * Admins cannot mutate owner memberships (instance ROLE_ADMIN may).
     *
     * @throws RuntimeException
     */
    private function assertCanMutateTarget(User $actor, Project $project, ProjectMembership $target): void
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $access = $this->projectAccess->resolveAccess($project, $actor);
        if (!$access instanceof ProjectAccess) {
            throw new RuntimeException('forbidden');
        }

        if (ProjectRole::Admin === $access->role && ProjectRole::Owner === $target->getRole()) {
            throw new RuntimeException('cannot_manage_owner');
        }
    }

    /** Count direct (not group-derived) owner memberships on the project. */
    private function countDirectOwners(Project $project): int
    {
        $count = 0;
        foreach ($project->getMemberships() as $membership) {
            if (ProjectRole::Owner === $membership->getRole()) {
                ++$count;
            }
        }

        return $count;
    }
}
