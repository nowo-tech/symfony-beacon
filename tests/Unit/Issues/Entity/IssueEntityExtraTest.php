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
}
