<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Entity\PasswordHistory;
use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\Exception\AccountAnonymizeException;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\AccountAnonymizer;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Notifications\Entity\PushSubscription;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectMembershipRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Repository\SocialLoginAccountRepository;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountAnonymizerTest extends TestCase
{
    private EntityManagerInterface&Stub $entityManager;
    private UserRepository&Stub $userRepository;
    private ProjectMembershipRepository&Stub $membershipRepository;
    private PushSubscriptionRepository&Stub $pushRepository;
    private SocialLoginAccountRepository&Stub $socialRepository;
    private UserPasswordHasherInterface&Stub $passwordHasher;
    /** @var list<UserAction> */
    private array $persisted = [];
    private int $flushCount = 0;
    private AccountAnonymizer $anonymizer;
    /** @var list<object> */
    private array $removed = [];

    protected function setUp(): void
    {
        $this->persisted = [];
        $this->removed = [];
        $this->flushCount = 0;
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof UserAction) {
                $this->persisted[] = $entity;
            }
        });
        $this->entityManager->method('remove')->willReturnCallback(function (object $entity): void {
            $this->removed[] = $entity;
        });
        $this->entityManager->method('flush')->willReturnCallback(function (): void {
            ++$this->flushCount;
        });
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->pushRepository = $this->createStub(PushSubscriptionRepository::class);
        $this->socialRepository = $this->createStub(SocialLoginAccountRepository::class);
        $this->passwordHasher = $this->createStub(UserPasswordHasherInterface::class);

        $this->pushRepository->method('findByUser')->willReturn([]);
        $this->socialRepository->method('findBy')->willReturn([]);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-random');
        $this->membershipRepository->method('findByUser')->willReturn([]);
        $this->userRepository->method('countAdmins')->willReturn(2);

        $this->rebuildAnonymizer();
    }

    public function testAlreadyAnonymizedThrows(): void
    {
        $subject = new User()->setEmail('a@example.com')->setAnonymizedAt(new DateTimeImmutable());
        $this->expectException(AccountAnonymizeException::class);
        $this->expectExceptionMessage(AccountAnonymizeException::ALREADY_ANONYMIZED);

        $this->anonymizer->anonymize($subject, new User());
    }

    public function testSoleOwnerBlocksAnonymize(): void
    {
        $subject = $this->user(10, 'owner@example.com');
        $project = $this->project(5, 'Solo');
        $membership = new ProjectMembership()
            ->setProject($project)
            ->setUser($subject)
            ->setRole(ProjectRole::Owner);
        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->membershipRepository->method('findByUser')->willReturn([$membership]);
        $this->membershipRepository->method('countOwnersByProjectIds')->willReturn([5 => 1]);
        $this->rebuildAnonymizer();

        try {
            $this->anonymizer->anonymize($subject, new User());
            self::fail('Expected AccountAnonymizeException');
        } catch (AccountAnonymizeException $e) {
            self::assertSame(AccountAnonymizeException::SOLE_OWNER, $e->reasonCode);
            self::assertSame([['uuid' => $project->getUuid(), 'name' => 'Solo']], $e->soleOwnerProjects);
        }
    }

    public function testLastAdminBlocksAnonymize(): void
    {
        $subject = $this->user(1, 'admin@example.com')->setRoles(['ROLE_ADMIN']);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->userRepository->method('countAdmins')->willReturn(1);
        $this->rebuildAnonymizer();

        $this->expectException(AccountAnonymizeException::class);
        $this->expectExceptionMessage(AccountAnonymizeException::LAST_ADMIN);

        $this->anonymizer->anonymize($subject, new User());
    }

    public function testAnonymizeScrubsIdentifiersAndRecordsAction(): void
    {
        $subject = $this->user(3, 'person@corp.example')
            ->setDisplayName('Person')
            ->setSlackUserId('U123')
            ->setPhone('+15551212')
            ->setRoles(['ROLE_USER']);
        $actor = $this->user(9, 'admin@example.com');
        $uuid = $subject->getUuid();

        $this->anonymizer->anonymize($subject, $actor);

        self::assertSame(1, $this->flushCount);
        self::assertSame('anonymized-'.$uuid.'@invalid.local', $subject->getEmail());
        self::assertSame('Anonymized user', $subject->getDisplayName());
        self::assertNull($subject->getSlackUserId());
        self::assertNull($subject->getPhone());
        self::assertFalse($subject->isEnabled());
        self::assertTrue($subject->isAnonymized());
        self::assertSame(['ROLE_USER'], $subject->getRoles());
        self::assertSame('hashed-random', $subject->getPassword());
        self::assertCount(1, $this->persisted);
        self::assertSame(UserActionType::UserAnonymized, $this->persisted[0]->getAction());
        self::assertSame(
            [
                'previous_email_domain' => 'corp.example',
                'uuid' => $uuid,
            ],
            $this->persisted[0]->getContext(),
        );
    }

    public function testSoleOwnerProjectsIgnoresCoOwnedAndNonOwners(): void
    {
        $user = $this->user(1, 'u@example.com');
        $solo = $this->project(1, 'Solo');
        $shared = $this->project(2, 'Shared');
        $memberOnly = $this->project(3, 'Member');

        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->membershipRepository->method('findByUser')->willReturn([
            new ProjectMembership()->setProject($solo)->setUser($user)->setRole(ProjectRole::Owner),
            new ProjectMembership()->setProject($shared)->setUser($user)->setRole(ProjectRole::Owner),
            new ProjectMembership()->setProject($memberOnly)->setUser($user)->setRole(ProjectRole::Member),
        ]);
        $this->membershipRepository->method('countOwnersByProjectIds')->willReturn([1 => 1, 2 => 2]);
        $this->rebuildAnonymizer();

        self::assertSame(
            [['uuid' => $solo->getUuid(), 'name' => 'Solo']],
            $this->anonymizer->soleOwnerProjects($user),
        );
        self::assertFalse($this->anonymizer->isLastAdmin($user));
    }

    public function testAnonymizeRemovesPasswordHistoryPushSubscriptionsAndSocialAccounts(): void
    {
        $subject = $this->user(7, 'invalid-email')
            ->setDisplayName('Person')
            ->setRoles(['ROLE_USER']);
        $actor = $this->user(8, 'admin@example.com');

        $history = new PasswordHistory()
            ->setPassword('old-hash')
            ->setCreatedAt(new DateTimeImmutable('2026-08-01 00:00:00'));
        $subject->addPasswordHistory($history);

        $subscription = new PushSubscription($subject)->setSubscription('https://push.example.test/1', 'key', 'auth');
        $socialAccount = new stdClass();

        $this->pushRepository = $this->createStub(PushSubscriptionRepository::class);
        $this->pushRepository->method('findByUser')->willReturn([$subscription]);
        $this->socialRepository = $this->createStub(SocialLoginAccountRepository::class);
        $this->socialRepository->method('findBy')->willReturn([$socialAccount]);
        $this->rebuildAnonymizer();

        $this->anonymizer->anonymize($subject, $actor);

        self::assertCount(3, $this->removed);
        self::assertSame([], $subject->getPasswordHistory()->toArray());
        self::assertSame('unknown', $this->persisted[0]->getContext()['previous_email_domain']);
    }

    private function rebuildAnonymizer(): void
    {
        $this->anonymizer = new AccountAnonymizer(
            $this->entityManager,
            $this->userRepository,
            $this->membershipRepository,
            $this->pushRepository,
            $this->socialRepository,
            $this->passwordHasher,
            new UserActionRecorder($this->entityManager, new RequestStack()),
        );
    }

    private function user(int $id, string $email): User
    {
        $user = new User()->setEmail($email);
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }

    private function project(int $id, string $name): Project
    {
        $project = new Project()->setName($name);
        new ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }
}
