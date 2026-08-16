<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Enum;

use App\Issues\Enum\IssueShowTab;
use PHPUnit\Framework\TestCase;

final class IssueShowTabTest extends TestCase
{
    public function testTabLabelsAndRouteRequirementCoverAllCases(): void
    {
        self::assertSame('issues.tab_main', IssueShowTab::Main->tabLabelKey());
        self::assertSame('issues.similar_title', IssueShowTab::Similar->tabLabelKey());
        self::assertSame('issues.history_title', IssueShowTab::History->tabLabelKey());
        self::assertSame('main|similar|history', IssueShowTab::routeRequirement());
    }
}
