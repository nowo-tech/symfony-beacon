<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Enum\ProjectRole;
use App\Project\Exception\ProjectAccessException;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectGroupAccessManager;
use App\Project\Service\ProjectMembershipPolicy;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectGroupAccessManagerTest extends TestCase
{
    private ProjectGroupAccessRepository&Stub $groupAccessRepository;
    private ProjectMembershipRepository&Stub $membershipRepository;
    private UserGroupMembershipRepository&Stub $groupMembershipRepository;
    private EntityManagerInterface&Stub $entityManager;
    private int $flushCount = 0;
    /** @var list<object> */
    private array $persisted = [];
    private ProjectGroupAccessManager $manager;

    protected function setUp(): void
    {
        $this->flushCount = 0;
        $this->persisted = [];
        $this->groupAccessRepository = $this->createStub(ProjectGroupAccessRepository::class);
        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->groupMembershipRepository = $this->createStub(UserGroupMembershipRepository::class);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('persist')->willReturnCallback(function (object $e): void {
            $this->persisted[] = $e;
        });
        $this->entityManager->method('remove');
        $this->entityManager->method('flush')->willReturnCallback(function (): void {
            ++$this->flushCount;
        });

        $access = new ProjectAccessService(
            $this->membershipRepository,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );
        $policy = new ProjectMembershipPolicy(
            $this->membershipRepository,
            $this->groupMembershipRepository,
            $access,
            $auth,
        );
        $this->manager = new ProjectGroupAccessManager(
            $this->groupAccessRepository,
            $policy,
            new UserActionRecorder($this->entityManager, new RequestStack()),
            $this->entityManager,
        );
    }

    public function testAddGroupHappyPathAndGuards(): void
    {
        $project = $this->project(1);
        $actor = $this->user(1);
        $group = $this->group(5, 'Eng');

        try {
            $this->manager->addGroup($project, $actor, $group, ProjectRole::Owner);
            self::fail('expected');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::INVALID_ROLE, $e->reasonCode);
        }

        $this->groupAccessRepository->method('findOneByProjectAndGroup')->willReturn(new ProjectGroupAccess());
        try {
            $this->manager->addGroup($project, $actor, $group, ProjectRole::Member);
            self::fail('expected');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::GROUP_ALREADY_LINKED, $e->reasonCode);
        }

        $this->groupAccessRepository = $this->createStub(ProjectGroupAccessRepository::class);
        $this->groupAccessRepository->method('findOneByProjectAndGroup')->willReturn(null);
        $this->rebuild(true);
        $access = $this->manager->addGroup($project, $actor, $group, ProjectRole::Admin);
        self::assertSame($group, $access->getUserGroup());
        self::assertSame(ProjectRole::Admin, $access->getRole());
        self::assertSame(1, $this->flushCount);
        self::assertTrue(array_any(
            $this->persisted,
            static fn (object $e): bool => $e instanceof UserAction
                && UserActionType::ProjectGroupLinked === $e->getAction(),
        ));
    }

    public function testChangeAndRemoveGroup(): void
    {
        $project = $this->project(1);
        $actor = $this->user(1);
        $group = $this->group(2, 'QA');
        $target = new ProjectGroupAccess()->setProject($project)->setUserGroup($group)->setRole(ProjectRole::Member);
        $project->addGroupAccess($target);

        $this->manager->changeGroupRole($project, $actor, $target, ProjectRole::Admin);
        self::assertSame(ProjectRole::Admin, $target->getRole());

        $wrong = new ProjectGroupAccess()->setProject($this->project(9))->setUserGroup($group)->setRole(ProjectRole::Member);
        try {
            $this->manager->changeGroupRole($project, $actor, $wrong, ProjectRole::Viewer);
            self::fail('expected');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::WRONG_PROJECT, $e->reasonCode);
        }

        $this->manager->removeGroup($project, $actor, $target);
        self::assertGreaterThanOrEqual(2, $this->flushCount);
    }

    public function testAssignableRolesAndLinkAssertion(): void
    {
        $project = new Project();
        $actor = new User();
        $group = new UserGroup();
        $this->groupMembershipRepository->method('findOneByGroupAndUser')->willReturn(new UserGroupMembership());

        self::assertSame(
            [ProjectRole::Admin, ProjectRole::Member, ProjectRole::Viewer],
            $this->manager->assignableGroupRoles($actor, $project),
        );
        $this->manager->assertActorCanLinkGroup($actor, $group, $project);
        self::assertSame(ProjectRole::Admin, $this->manager->assignableGroupRoles($actor, $project)[0]);
    }

    private function rebuild(bool $adminAuth = true): void
    {
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn($adminAuth);
        $access = new ProjectAccessService(
            $this->membershipRepository,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );
        $policy = new ProjectMembershipPolicy(
            $this->membershipRepository,
            $this->groupMembershipRepository,
            $access,
            $auth,
        );
        $this->manager = new ProjectGroupAccessManager(
            $this->groupAccessRepository,
            $policy,
            new UserActionRecorder($this->entityManager, new RequestStack()),
            $this->entityManager,
        );
    }

    private function project(int $id): Project
    {
        $project = new Project()->setName('P'.$id)->setSlug('p'.$id);
        new ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }

    private function user(int $id): User
    {
        $user = new User()->setEmail('u'.$id.'@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }

    private function group(int $id, string $name): UserGroup
    {
        $group = new UserGroup()->setName($name)->setSlug(strtolower($name));
        new ReflectionProperty(UserGroup::class, 'id')->setValue($group, $id);

        return $group;
    }
}
