<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Enum\ProjectRole;
use App\Project\Exception\ProjectAccessException;
use App\Project\Repository\ProjectGroupAccessRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Links, updates, and unlinks user groups on a project.
 *
 * Domain failures raise {@see ProjectAccessException}.
 */
final readonly class ProjectGroupAccessManager
{
    public function __construct(
        private ProjectGroupAccessRepository $groupAccessRepository,
        private ProjectMembershipPolicy $policy,
        private UserActionRecorder $actionRecorder,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<ProjectRole>
     */
    public function assignableGroupRoles(User $actor, Project $project): array
    {
        return $this->policy->assignableGroupRoles($actor, $project);
    }

    /**
     * @throws ProjectAccessException
     */
    public function assertActorCanLinkGroup(User $actor, UserGroup $group, Project $project): void
    {
        $this->policy->assertActorCanLinkGroup($actor, $group, $project);
    }

    /**
     * Link a user group to the project (admin/member only).
     *
     * @throws ProjectAccessException
     */
    public function addGroup(Project $project, User $actor, UserGroup $group, ProjectRole $role): ProjectGroupAccess
    {
        $this->policy->assertActorCanManage($project, $actor);
        $this->policy->assertActorCanLinkGroup($actor, $group, $project);
        if (\in_array($role, [ProjectRole::Owner, ProjectRole::Full], true)) {
            throw ProjectAccessException::of(ProjectAccessException::INVALID_ROLE);
        }
        $this->policy->assertAssignableGroupRole($actor, $project, $role);

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
     * Change the role of a linked group (admin/member only).
     *
     * @throws ProjectAccessException
     */
    public function changeGroupRole(Project $project, User $actor, ProjectGroupAccess $target, ProjectRole $role): void
    {
        $this->policy->assertActorCanManage($project, $actor);
        if ($target->getProject()?->getId() !== $project->getId()) {
            throw ProjectAccessException::of(ProjectAccessException::WRONG_PROJECT);
        }
        if (\in_array($role, [ProjectRole::Owner, ProjectRole::Full], true)) {
            throw ProjectAccessException::of(ProjectAccessException::INVALID_ROLE);
        }
        $this->policy->assertAssignableGroupRole($actor, $project, $role);

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
        $this->policy->assertActorCanManage($project, $actor);
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
}
