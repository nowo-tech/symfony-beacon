<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueHistoryEntry;
use App\Issues\Enum\IssueStatus;
use App\Issues\IssueHistoryKind;
use App\Issues\Service\IssueHistoryRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class IssueHistoryRecorderTest extends TestCase
{
    public function testAssigneeChangeNoOpWhenSameUserIds(): void
    {
        $user = $this->userWithId(7);
        $issue = new Issue();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        new IssueHistoryRecorder($em)->recordAssigneeChange($issue, $user, $user, null);

        self::assertCount(0, $issue->getHistoryEntries());
    }

    public function testAssigneeChangeNoOpWhenBothNull(): void
    {
        $issue = new Issue();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        new IssueHistoryRecorder($em)->recordAssigneeChange($issue, null, null, null);

        self::assertCount(0, $issue->getHistoryEntries());
    }

    public function testAssigneeChangePersistsEntry(): void
    {
        $from = $this->userWithId(1);
        $to = $this->userWithId(2);
        $actor = $this->userWithId(3);
        $issue = new Issue();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static function (object $entry) use ($issue, $from, $to, $actor): bool {
                self::assertInstanceOf(IssueHistoryEntry::class, $entry);
                self::assertSame($issue, $entry->getIssue());
                self::assertSame(IssueHistoryKind::AssigneeChanged, $entry->getKind());
                self::assertSame($from, $entry->getFromAssignee());
                self::assertSame($to, $entry->getToAssignee());
                self::assertSame($actor, $entry->getActor());

                return true;
            },
        ));

        new IssueHistoryRecorder($em)->recordAssigneeChange($issue, $from, $to, $actor);

        self::assertCount(1, $issue->getHistoryEntries());
    }

    public function testStatusChangeNoOpWhenSameStatus(): void
    {
        $issue = new Issue();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        new IssueHistoryRecorder($em)->recordStatusChange(
            $issue,
            IssueStatus::Unresolved,
            IssueStatus::Unresolved,
            null,
        );

        self::assertCount(0, $issue->getHistoryEntries());
    }

    public function testStatusChangePersistsEntry(): void
    {
        $actor = $this->userWithId(9);
        $issue = new Issue();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static function (object $entry) use ($issue, $actor): bool {
                self::assertInstanceOf(IssueHistoryEntry::class, $entry);
                self::assertSame($issue, $entry->getIssue());
                self::assertSame(IssueHistoryKind::StatusChanged, $entry->getKind());
                self::assertSame(IssueStatus::Unresolved, $entry->getFromStatus());
                self::assertSame(IssueStatus::Resolved, $entry->getToStatus());
                self::assertSame($actor, $entry->getActor());

                return true;
            },
        ));

        new IssueHistoryRecorder($em)->recordStatusChange(
            $issue,
            IssueStatus::Unresolved,
            IssueStatus::Resolved,
            $actor,
        );

        self::assertCount(1, $issue->getHistoryEntries());
    }

    private function userWithId(int $id): User
    {
        $user = new User();
        $user->setEmail(\sprintf('user%d@example.com', $id));

        $property = new ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }
}
