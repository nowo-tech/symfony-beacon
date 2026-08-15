<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Exception\ProjectAccessException;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Tests\Support\ProjectAccessServiceFactory;
use App\Project\Service\ProjectMembershipPolicy;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectMembershipPolicyTest extends TestCase
{
    private ProjectMembershipRepository&Stub $membershipRepository;
    private UserGroupMembershipRepository&Stub $groupMembershipRepository;
    private AuthorizationCheckerInterface&Stub $authorizationChecker;
    private ProjectMembershipPolicy $policy;

    protected function setUp(): void
    {
        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->groupMembershipRepository = $this->createStub(UserGroupMembershipRepository::class);
        $this->authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);

        $accessService = ProjectAccessServiceFactory::create(
            $this->membershipRepository,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $this->authorizationChecker,
            new RequestStack(),
        );

        $this->policy = new ProjectMembershipPolicy(
            $this->membershipRepository,
            $this->groupMembershipRepository,
            $accessService,
            $this->authorizationChecker,
        );
    }

    public function testInstanceAdminAssignableRolesIncludeOwnerAndGroupRolesExcludeOwner(): void
    {
        $this->authorizationChecker->method('isGranted')->willReturn(true);
        $actor = new User();
        $project = new Project();

        self::assertSame(
            [ProjectRole::Owner, ProjectRole::Full, ProjectRole::Admin, ProjectRole::Member, ProjectRole::Viewer],
            $this->policy->assignableRoles($actor, $project),
        );
        self::assertSame(
            [ProjectRole::Admin, ProjectRole::Member, ProjectRole::Viewer],
            $this->policy->assignableGroupRoles($actor, $project),
        );
    }

    public function testProjectAdminAssignableRolesExcludeOwnerFull(): void
    {
        $this->authorizationChecker->method('isGranted')->willReturn(false);
        $actor = new User();
        $project = new Project();
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn(
            new ProjectMembership()->setProject($project)->setUser($actor)->setRole(ProjectRole::Admin),
        );

        self::assertSame(
            [ProjectRole::Admin, ProjectRole::Member, ProjectRole::Viewer],
            $this->policy->assignableRoles($actor, $project),
        );
    }

    public function testAssertActorCanManageThrowsWithoutAccess(): void
    {
        $this->authorizationChecker->method('isGranted')->willReturn(false);
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn(null);

        $this->expectException(ProjectAccessException::class);
        $this->policy->assertActorCanManage(new Project(), new User());
    }

    public function testAssertCanMutateTargetBlocksAdminChangingOwner(): void
    {
        $this->authorizationChecker->method('isGranted')->willReturn(false);
        $actor = new User();
        $project = $this->project(1);
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn(
            new ProjectMembership()->setProject($project)->setUser($actor)->setRole(ProjectRole::Admin),
        );
        $target = new ProjectMembership()
            ->setProject($project)
            ->setUser(new User())
            ->setRole(ProjectRole::Owner);

        $this->expectException(ProjectAccessException::class);
        $this->policy->assertCanMutateTarget($actor, $project, $target);
    }

    public function testAssertSameProjectAndCountOwners(): void
    {
        $project = $this->project(5);
        $other = $this->project(9);
        $membership = new ProjectMembership()->setProject($other)->setUser(new User());

        try {
            $this->policy->assertSameProject($project, $membership);
            self::fail('Expected ProjectAccessException');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::WRONG_PROJECT, $e->reasonCode);
        }

        $this->membershipRepository->method('countOwnersByProjectIds')->willReturn([5 => 2]);
        self::assertSame(2, $this->policy->countDirectOwners($project));
        self::assertSame(0, $this->policy->countDirectOwners(new Project()));
    }

    public function testAssertActorCanLinkGroupAllowsGroupMember(): void
    {
        self::expectNotToPerformAssertions();
        $this->authorizationChecker->method('isGranted')->willReturn(false);
        $actor = new User();
        $project = new Project();
        $group = new UserGroup();
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn(
            new ProjectMembership()->setProject($project)->setUser($actor)->setRole(ProjectRole::Admin),
        );
        $this->groupMembershipRepository->method('findOneByGroupAndUser')->willReturn(new UserGroupMembership());

        $this->policy->assertActorCanLinkGroup($actor, $group, $project);
    }

    private function project(int $id): Project
    {
        $project = new Project();
        $project->setSlug('p'.$id);
        $project->setName('P'.$id);
        new ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }
}
