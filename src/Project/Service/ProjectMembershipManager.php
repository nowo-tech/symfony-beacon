<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Exception\ProjectAccessException;
use App\Project\Repository\ProjectMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Adds, updates, and removes direct project memberships.
 *
 * Group links live in {@see ProjectGroupAccessManager}. Shared authz rules are in
 * {@see ProjectMembershipPolicy}.
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
        private ProjectMembershipPolicy $policy,
        private UserActionRecorder $actionRecorder,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<ProjectRole>
     */
    public function assignableRoles(User $actor, Project $project): array
    {
        return $this->policy->assignableRoles($actor, $project);
    }

    /**
     * Add an existing enabled user by email as a direct project member.
     *
     * @throws ProjectAccessException
     */
    public function addByEmail(Project $project, User $actor, string $email, ProjectRole $role): ProjectMembership
    {
        $this->policy->assertActorCanManage($project, $actor);
        $this->policy->assertAssignableRole($actor, $project, $role);

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
        $this->policy->assertActorCanManage($project, $actor);
        $this->policy->assertSameProject($project, $target);
        $this->policy->assertCanMutateTarget($actor, $project, $target);
        $this->policy->assertAssignableRole($actor, $project, $role);

        if (ProjectRole::Owner === $target->getRole() && ProjectRole::Owner !== $role && $this->policy->countDirectOwners($project) <= 1) {
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
        $this->policy->assertActorCanManage($project, $actor);
        $this->policy->assertSameProject($project, $target);
        $this->policy->assertCanMutateTarget($actor, $project, $target);

        if (ProjectRole::Owner === $target->getRole() && $this->policy->countDirectOwners($project) <= 1) {
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
        $this->policy->assertActorCanTransferOwnership($project, $actor);
        $this->policy->assertSameProject($project, $target);

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
     * Activate or deactivate a direct membership (does not delete the row).
     *
     * @throws ProjectAccessException
     */
    public function setActive(Project $project, User $actor, ProjectMembership $target, bool $active): void
    {
        $this->policy->assertActorCanManage($project, $actor);
        $this->policy->assertCanMutateTarget($actor, $project, $target);

        if ($target->getProject()?->getId() !== $project->getId()) {
            throw ProjectAccessException::of(ProjectAccessException::FORBIDDEN);
        }

        if (!$active && ProjectRole::Owner === $target->getRole() && $this->policy->countDirectOwners($project) <= 1) {
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
