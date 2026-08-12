<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Exception\ProjectAccessException;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessFlashKeys;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectMembershipManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectMembershipManagerTest extends TestCase
{
    private ProjectMembershipRepository&MockObject $membershipRepository;
    private ProjectGroupAccessRepository&MockObject $groupAccessRepository;
    private UserGroupMembershipRepository&MockObject $userGroupMembershipRepository;
    private AuthorizationCheckerInterface&MockObject $authorizationChecker;
    private ProjectMembershipManager $manager;

    protected function setUp(): void
    {
        $this->membershipRepository = $this->createMock(ProjectMembershipRepository::class);
        $this->groupAccessRepository = $this->createMock(ProjectGroupAccessRepository::class);
        $this->userGroupMembershipRepository = $this->createMock(UserGroupMembershipRepository::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->authorizationChecker->expects(self::any())->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $projectAccess = new ProjectAccessService(
            $this->membershipRepository,
            $this->groupAccessRepository,
            $this->createMock(ProjectShareLinkRepository::class),
            $this->authorizationChecker,
            new RequestStack(),
        );

        $actionRecorder = new ReflectionClass(UserActionRecorder::class)->newInstanceWithoutConstructor();

        $this->manager = new ProjectMembershipManager(
            $this->createMock(UserRepository::class),
            $this->membershipRepository,
            $this->groupAccessRepository,
            $this->userGroupMembershipRepository,
            $projectAccess,
            $actionRecorder,
            $this->authorizationChecker,
            $this->createMock(EntityManagerInterface::class),
        );
    }

    public function testCannotRemoveLastOwner(): void
    {
        $project = $this->projectWithId(1);
        $owner = $this->userWithId(1);
        $membership = $this->membership($project, $owner, ProjectRole::Owner);

        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn($membership);
        $this->membershipRepository->method('countOwnersByProjectIds')->willReturn([1 => 1]);
        $this->groupAccessRepository->method('findHighestGroupRoleForUser')->willReturn(null);

        try {
            $this->manager->remove($project, $owner, $membership);
            self::fail('Expected ProjectAccessException');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::LAST_OWNER, $e->reasonCode);
        }
    }

    public function testCannotRemoveFullMember(): void
    {
        $project = $this->projectWithId(1);
        $owner = $this->userWithId(1);
        $full = $this->userWithId(2);
        $ownerMembership = $this->membership($project, $owner, ProjectRole::Owner);
        $fullMembership = $this->membership($project, $full, ProjectRole::Full);

        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn($ownerMembership);
        $this->membershipRepository->method('countOwnersByProjectIds')->willReturn([1 => 1]);
        $this->groupAccessRepository->method('findHighestGroupRoleForUser')->willReturn(null);

        try {
            $this->manager->remove($project, $owner, $fullMembership);
            self::fail('Expected ProjectAccessException');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::CANNOT_REMOVE_FULL, $e->reasonCode);
        }
    }

    public function testGroupLinkForbiddenForAdminOutsideGroup(): void
    {
        $project = $this->projectWithId(1);
        $admin = $this->userWithId(20);
        $adminMembership = $this->membership($project, $admin, ProjectRole::Admin);
        $group = new UserGroup();
        new ReflectionProperty(UserGroup::class, 'id')->setValue($group, 5);

        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn($adminMembership);
        $this->groupAccessRepository->method('findHighestGroupRoleForUser')->willReturn(null);
        $this->userGroupMembershipRepository->method('findOneByGroupAndUser')->willReturn(null);

        try {
            $this->manager->assertActorCanLinkGroup($admin, $group, $project);
            self::fail('Expected ProjectAccessException');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::GROUP_LINK_FORBIDDEN, $e->reasonCode);
        }
    }

    public function testCannotTransferToSelf(): void
    {
        $project = $this->projectWithId(1);
        $owner = $this->userWithId(1);
        $ownerMembership = $this->membership($project, $owner, ProjectRole::Owner);

        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn($ownerMembership);
        $this->groupAccessRepository->method('findHighestGroupRoleForUser')->willReturn(null);

        try {
            $this->manager->transferOwnership($project, $owner, $ownerMembership);
            self::fail('Expected ProjectAccessException');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::CANNOT_TRANSFER_TO_SELF, $e->reasonCode);
        }
    }

    public function testFlashKeysCoverKnownCodes(): void
    {
        foreach ([
            ProjectAccessException::USER_NOT_FOUND,
            ProjectAccessException::LAST_OWNER,
            ProjectAccessException::GROUP_LINK_FORBIDDEN,
            ProjectAccessException::CANNOT_TRANSFER_TO_SELF,
            ProjectAccessException::CANNOT_REMOVE_FULL,
        ] as $code) {
            self::assertNotSame('flash.project.member_error', ProjectAccessFlashKeys::forCode($code), $code);
            self::assertSame(
                ProjectAccessFlashKeys::forCode($code),
                ProjectAccessFlashKeys::forException(ProjectAccessException::of($code)),
            );
        }
    }

    private function projectWithId(int $id): Project
    {
        $project = new Project();
        new ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }

    private function userWithId(int $id): User
    {
        $user = new User();
        $user->setEmail('u'.$id.'@example.com');
        $user->setDisplayName('User '.$id);
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }

    private function membership(Project $project, User $user, ProjectRole $role): ProjectMembership
    {
        $membership = new ProjectMembership();
        $membership->setUser($user);
        $membership->setRole($role);
        $project->addMembership($membership);

        return $membership;
    }
}
