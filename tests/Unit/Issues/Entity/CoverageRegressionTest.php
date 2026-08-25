<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Entity;

use App\Identity\Entity\User;
use App\Issues\Entity\EventTag;
use App\Issues\Entity\IssueComment;
use App\Issues\Entity\IssueHistoryEntry;
use App\Issues\Entity\IssueMention;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CoverageRegressionTest extends TestCase
{
    public function testEntityIdsStartNullAndMentionDefaultsToUnread(): void
    {
        $eventTag = new EventTag();
        $comment = new IssueComment();
        $history = new IssueHistoryEntry();
        $mention = new IssueMention();

        self::assertNull($eventTag->getId());
        self::assertNull($comment->getId());
        self::assertNull($history->getId());
        self::assertNull($mention->getMentionedUser());
        self::assertNull($mention->getReadAt());
        self::assertTrue($mention->isUnread());
        self::assertGreaterThan(0, $history->getCreatedAt()->getTimestamp());

        $user = new User()->setEmail('mention@example.com');
        $readAt = new DateTimeImmutable('2026-08-16T11:00:00+00:00');
        $mention->setMentionedUser($user)->markRead($readAt);

        self::assertSame($user, $mention->getMentionedUser());
        self::assertSame($readAt, $mention->getReadAt());
        self::assertFalse($mention->isUnread());
    }
}
