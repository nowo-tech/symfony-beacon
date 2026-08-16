<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Enum\IssueStatus;
use App\Issues\Service\IssueStatusTransition;
use PHPUnit\Framework\TestCase;

final class IssueStatusTransitionTest extends TestCase
{
    public function testSameStatusAllowed(): void
    {
        self::assertTrue(IssueStatusTransition::canTransition(IssueStatus::Unresolved, IssueStatus::Unresolved));
    }

    public function testUnresolvedToResolvedAndIgnored(): void
    {
        self::assertTrue(IssueStatusTransition::canTransition(IssueStatus::Unresolved, IssueStatus::Resolved));
        self::assertTrue(IssueStatusTransition::canTransition(IssueStatus::Unresolved, IssueStatus::Ignored));
    }

    public function testAllowedTargetsCoverLifecycle(): void
    {
        self::assertSame(
            [IssueStatus::Resolved, IssueStatus::Ignored],
            IssueStatusTransition::allowedTargets(IssueStatus::Unresolved),
        );
        self::assertContains(IssueStatus::Unresolved, IssueStatusTransition::allowedTargets(IssueStatus::Resolved));
        self::assertContains(IssueStatus::Unresolved, IssueStatusTransition::allowedTargets(IssueStatus::Ignored));
    }

}
