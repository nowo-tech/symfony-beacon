<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Exception\ProjectAccessException;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectMembershipManager;
use App\Project\Service\ProjectMembershipPolicy;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectMembershipManagerTest extends TestCase
{
    private UserRepository&Stub $userRepository;
    private ProjectMembershipRepository&Stub $membershipRepository;
    private AuthorizationCheckerInterface&Stub $authorizationChecker;
    private EntityManagerInterface&Stub $entityManager;
    /** @var list<object> */
    private array $persisted = [];
    private int $flushCount = 0;
    private ProjectMembershipManager $manager;

    protected function setUp(): void
    {
        $this->persisted = [];
        $this->flushCount = 0;
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $this->authorizationChecker->method('isGranted')->willReturn(true);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $this->entityManager->method('remove');
        $this->entityManager->method('flush')->willReturnCallback(function (): void {
            ++$this->flushCount;
        });

        $access = new ProjectAccessService(
            $this->membershipRepository,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $this->authorizationChecker,
            new RequestStack(),
        );
        $policy = new ProjectMembershipPolicy(
            $this->membershipRepository,
            $this->createStub(UserGroupMembershipRepository::class),
            $access,
            $this->authorizationChecker,
        );

        $this->manager = new ProjectMembershipManager(
            $this->userRepository,
            $this->membershipRepository,
            $policy,
            new UserActionRecorder($this->entityManager, new RequestStack()),
            $this->entityManager,
        );
    }

    public function testAddByEmailHappyPathAndFailures(): void
    {
        $project = $this->project(1);
        $actor = $this->user(1, 'admin@example.com');

        $this->userRepository->method('findOneByEmail')->willReturn(null);
        try {
            $this->manager->addByEmail($project, $actor, 'missing@example.com', ProjectRole::Member);
            self::fail('expected');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::USER_NOT_FOUND, $e->reasonCode);
        }

        $disabled = $this->user(2, 'd@example.com')->setEnabled(false);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->userRepository->method('findOneByEmail')->willReturn($disabled);
        $this->rebuild();
        try {
            $this->manager->addByEmail($project, $actor, 'd@example.com', ProjectRole::Member);
            self::fail('expected');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::USER_DISABLED, $e->reasonCode);
        }

        $member = $this->user(3, 'm@example.com');
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->userRepository->method('findOneByEmail')->willReturn($member);
        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn(
            (new ProjectMembership())->setProject($project)->setUser($member)->setRole(ProjectRole::Viewer),
        );
        $this->rebuild();
        try {
            $this->manager->addByEmail($project, $actor, 'm@example.com', ProjectRole::Member);
            self::fail('expected');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::ALREADY_MEMBER, $e->reasonCode);
        }

        $fresh = $this->user(4, 'new@example.com');
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->userRepository->method('findOneByEmail')->willReturn($fresh);
        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn(null);
        $this->rebuild();
        $created = $this->manager->addByEmail($project, $actor, 'new@example.com', ProjectRole::Member);
        self::assertSame($fresh, $created->getUser());
        self::assertSame(ProjectRole::Member, $created->getRole());
        self::assertSame(1, $this->flushCount);
    }

    public function testChangeRoleRemoveTransferAndSetActive(): void
    {
        $project = $this->project(1);
        $actor = $this->user(1, 'owner@example.com');
        $targetUser = $this->user(2, 'member@example.com');
        $actorMembership = (new ProjectMembership())->setProject($project)->setUser($actor)->setRole(ProjectRole::Owner);
        $target = (new ProjectMembership())->setProject($project)->setUser($targetUser)->setRole(ProjectRole::Member);
        $project->addMembership($actorMembership);
        $project->addMembership($target);

        $this->membershipRepository->method('findOneByProjectAndUser')->willReturnCallback(
            static function (Project $p, User $u) use ($actor, $actorMembership, $targetUser, $target): ?ProjectMembership {
                if ($u === $actor) {
                    return $actorMembership;
                }
                if ($u === $targetUser) {
                    return $target;
                }

                return null;
            },
        );
        $this->membershipRepository->method('countOwnersByProjectIds')->willReturn([1 => 2]);

        $this->manager->changeRole($project, $actor, $target, ProjectRole::Admin);
        self::assertSame(ProjectRole::Admin, $target->getRole());

        $this->manager->setActive($project, $actor, $target, false);
        self::assertFalse($target->isActive());
        $this->manager->setActive($project, $actor, $target, false); // no-op
        $this->manager->setActive($project, $actor, $target, true);
        self::assertTrue($target->isActive());

        $this->manager->transferOwnership($project, $actor, $target);
        self::assertSame(ProjectRole::Owner, $target->getRole());
        self::assertSame(ProjectRole::Full, $actorMembership->getRole());

        $removable = (new ProjectMembership())->setProject($project)->setUser($this->user(9, 'v@example.com'))->setRole(ProjectRole::Viewer);
        $project->addMembership($removable);
        $this->manager->remove($project, $actor, $removable);
        self::assertGreaterThan(0, $this->flushCount);
        self::assertTrue(array_any(
            $this->persisted,
            static fn (object $e): bool => $e instanceof \App\Identity\Entity\UserAction
                && \in_array($e->getAction(), [
                    UserActionType::ProjectMemberRoleChanged,
                    UserActionType::ProjectOwnershipTransferred,
                    UserActionType::ProjectMemberRemoved,
                    UserActionType::ProjectMemberActivated,
                    UserActionType::ProjectMemberDeactivated,
                ], true),
        ));
    }

    public function testRemoveBlocksLastOwnerAndFull(): void
    {
        $project = $this->project(1);
        $actor = $this->user(1, 'owner@example.com');
        $ownerMembership = (new ProjectMembership())->setProject($project)->setUser($actor)->setRole(ProjectRole::Owner);
        $full = (new ProjectMembership())->setProject($project)->setUser($this->user(2, 'f@example.com'))->setRole(ProjectRole::Full);
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn($ownerMembership);
        $this->membershipRepository->method('countOwnersByProjectIds')->willReturn([1 => 1]);

        try {
            $this->manager->remove($project, $actor, $ownerMembership);
            self::fail('expected');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::LAST_OWNER, $e->reasonCode);
        }

        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn($ownerMembership);
        $this->membershipRepository->method('countOwnersByProjectIds')->willReturn([1 => 2]);
        $this->rebuild();
        try {
            $this->manager->remove($project, $actor, $full);
            self::fail('expected');
        } catch (ProjectAccessException $e) {
            self::assertSame(ProjectAccessException::CANNOT_REMOVE_FULL, $e->reasonCode);
        }
    }

    private function rebuild(): void
    {
        $access = new ProjectAccessService(
            $this->membershipRepository,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $this->authorizationChecker,
            new RequestStack(),
        );
        $policy = new ProjectMembershipPolicy(
            $this->membershipRepository,
            $this->createStub(UserGroupMembershipRepository::class),
            $access,
            $this->authorizationChecker,
        );
        $this->manager = new ProjectMembershipManager(
            $this->userRepository,
            $this->membershipRepository,
            $policy,
            new UserActionRecorder($this->entityManager, new RequestStack()),
            $this->entityManager,
        );
    }

    private function project(int $id): Project
    {
        $project = (new Project())->setName('P'.$id)->setSlug('p'.$id);
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, $id);

        return $project;
    }

    private function user(int $id, string $email): User
    {
        $user = (new User())->setEmail($email);
        (new ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
