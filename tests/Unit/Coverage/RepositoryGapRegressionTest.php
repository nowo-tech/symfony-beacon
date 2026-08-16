<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coverage;

use App\Identity\Entity\User;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Issues\Repository\EventTagRepository;
use App\Issues\Repository\IssueMentionRepository;
use App\Notifications\Entity\MemberAccountAlertEvent;
use App\Notifications\Entity\MemberProjectAlertEvent;
use App\Notifications\Entity\NotificationDeliveryAttempt;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectShareLink;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class RepositoryGapRegressionTest extends TestCase
{
    public function testUserGroupMembershipCountByGroupIdsMapsDatabaseRows(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn([
            ['groupId' => '5', 'cnt' => '2'],
        ]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(UserGroupMembershipRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repo->method('createQueryBuilder')->with('m')->willReturn($qb);

        self::assertSame([5 => 2, 7 => 0], $repo->countByGroupIds([5, 7]));
    }

    public function testIssueMentionInboxAppliesPositiveOffset(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->expects(self::once())->method('setFirstResult')->with(10)->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(IssueMentionRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repo->method('createQueryBuilder')->with('m')->willReturn($qb);

        self::assertSame([], $repo->findInboxForUser(new User(), [new Project()], false, 50, 10));
    }

    public function testEventTagRepositoryReturnsEmptyForBlankNeedleOrMissingProjectId(): void
    {
        $repo = $this->getMockBuilder(EventTagRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['getEntityManager'])
            ->getMock();
        $repo->expects(self::never())->method('getEntityManager');

        self::assertSame([], $repo->findIssueIdsMatchingTag(new Project(), ''));
    }

    public function testMemberAccountAlertEventRepositorySkipsRowsWithoutUserId(): void
    {
        $row = new MemberAccountAlertEvent()
            ->setUser(new User()->setEmail('alerts@example.com'))
            ->setEvent(MemberAlertEvent::IssueAssigned);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$row]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(MemberAccountAlertEventRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repo->method('createQueryBuilder')->with('e')->willReturn($qb);

        self::assertSame([], $repo->findIndexedByUserIds([1]));
    }

    public function testMemberProjectAlertEventRepositorySkipsRowsWithoutProjectOrUserId(): void
    {
        $user = new User()->setEmail('project-alerts@example.com');
        $project = new Project();

        $projectlessRow = new MemberProjectAlertEvent()
            ->setUser($user)
            ->setProject(new Project())
            ->setEvent(MemberAlertEvent::IssueAssigned);
        $userlessRow = new MemberProjectAlertEvent()
            ->setUser(new User()->setEmail('no-id@example.com'))
            ->setProject($project)
            ->setEvent(MemberAlertEvent::IssueAssigned);

        $queryByProject = $this->createMock(Query::class);
        $queryByProject->method('getResult')->willReturn([$projectlessRow]);
        $qbByProject = $this->createMock(QueryBuilder::class);
        $qbByProject->method('andWhere')->willReturnSelf();
        $qbByProject->method('setParameter')->willReturnSelf();
        $qbByProject->method('getQuery')->willReturn($queryByProject);

        $queryByUser = $this->createMock(Query::class);
        $queryByUser->method('getResult')->willReturn([$userlessRow]);
        $qbByUser = $this->createMock(QueryBuilder::class);
        $qbByUser->method('innerJoin')->willReturnSelf();
        $qbByUser->method('addSelect')->willReturnSelf();
        $qbByUser->method('andWhere')->willReturnSelf();
        $qbByUser->method('setParameter')->willReturnSelf();
        $qbByUser->method('getQuery')->willReturn($queryByUser);

        $repo = $this->getMockBuilder(MemberProjectAlertEventRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repo->method('createQueryBuilder')->with('e')->willReturnOnConsecutiveCalls($qbByProject, $qbByUser);

        self::assertSame([], $repo->findIndexedByProjectIdForUser($user, [$project]));
        self::assertSame([], $repo->findIndexedByUserIdsForProject($project, [1]));
    }

    public function testNotificationDeliveryAttemptRepositorySkipsRowsWithoutDestinationIdAndEmptyTrimResult(): void
    {
        $inputDestination = $this->destinationWithId(1);
        $attempt = new NotificationDeliveryAttempt();
        $attempt->setDestination(new NotificationDestination());

        $recentQuery = $this->createMock(Query::class);
        $recentQuery->method('getResult')->willReturn([$attempt]);
        $recentQb = $this->createMock(QueryBuilder::class);
        $recentQb->method('andWhere')->willReturnSelf();
        $recentQb->method('setParameter')->willReturnSelf();
        $recentQb->method('orderBy')->willReturnSelf();
        $recentQb->method('addOrderBy')->willReturnSelf();
        $recentQb->method('getQuery')->willReturn($recentQuery);

        $keepIdsQuery = $this->createMock(Query::class);
        $keepIdsQuery->method('getSingleColumnResult')->willReturn([]);
        $keepIdsQb = $this->createMock(QueryBuilder::class);
        $keepIdsQb->method('select')->willReturnSelf();
        $keepIdsQb->method('andWhere')->willReturnSelf();
        $keepIdsQb->method('setParameter')->willReturnSelf();
        $keepIdsQb->method('orderBy')->willReturnSelf();
        $keepIdsQb->method('addOrderBy')->willReturnSelf();
        $keepIdsQb->method('setMaxResults')->willReturnSelf();
        $keepIdsQb->method('getQuery')->willReturn($keepIdsQuery);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $repo = $this->getMockBuilder(NotificationDeliveryAttemptRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder', 'getEntityManager'])
            ->getMock();
        $repo->method('getEntityManager')->willReturn($em);
        $repo->method('createQueryBuilder')->with('a')->willReturnOnConsecutiveCalls($recentQb, $keepIdsQb);

        self::assertSame([1 => []], $repo->findRecentByDestinations([$inputDestination]));
        self::assertSame(0, $repo->trimOlderThanKeep($inputDestination, 5));
    }

    public function testProjectMembershipRepositoryReturnsEarlyForEmptyInputs(): void
    {
        $repo = $this->getMockBuilder(ProjectMembershipRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder', 'getEntityManager'])
            ->getMock();
        $repo->expects(self::never())->method('createQueryBuilder');
        $repo->expects(self::never())->method('getEntityManager');

        self::assertSame([], $repo->countOwnersByProjectIds([]));
        self::assertSame([], $repo->findUsersByProjects([new Project()]));
    }

    public function testProjectShareLinkRepositoryRejectsUseClaimWhenEntityIsNotPersisted(): void
    {
        $repo = $this->getMockBuilder(ProjectShareLinkRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['getEntityManager'])
            ->getMock();
        $repo->expects(self::never())->method('getEntityManager');

        self::assertFalse($repo->tryClaimUse(new ProjectShareLink()));
    }

    private function destinationWithId(int $id): NotificationDestination
    {
        $destination = new NotificationDestination();
        new ReflectionProperty(NotificationDestination::class, 'id')->setValue($destination, $id);

        return $destination;
    }
}
