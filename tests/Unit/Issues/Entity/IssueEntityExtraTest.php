<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Entity;

use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use PHPUnit\Framework\TestCase;

final class IssueEntityExtraTest extends TestCase
{
    public function testLevelEnumAndEventsCollectionAccessors(): void
    {
        $issue = new Issue();
        $issue->setLevel(IssueLevel::Warning);

        self::assertSame(IssueLevel::Warning, $issue->getLevelEnum());
        self::assertCount(0, $issue->getEvents());
    }

    public function testCulpritKeepsTypicalClassMethod(): void
    {
        $issue = new Issue();
        $long = 'App\\Repositories\\Eloquent\\AttendanceRepository::getAttendanceSummary';
        $issue->setCulprit($long);

        self::assertSame($long, $issue->getCulprit());
        $issue->setCulprit(str_repeat('a', 300));
        self::assertSame(Issue::CULPRIT_MAX_LENGTH, mb_strlen($issue->getCulprit()));
    }
}
