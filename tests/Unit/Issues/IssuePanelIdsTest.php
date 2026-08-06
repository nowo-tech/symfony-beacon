<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues;

use App\Issues\IssuePanelIds;
use PHPUnit\Framework\TestCase;

final class IssuePanelIdsTest extends TestCase
{
    public function testAllContainsStableIds(): void
    {
        $all = IssuePanelIds::all();

        self::assertContains(IssuePanelIds::STACKTRACE, $all);
        self::assertContains(IssuePanelIds::TRIAGE, $all);
        self::assertSame($all, array_values(array_unique($all)));
    }

    public function testDefaultCollapsed(): void
    {
        self::assertSame(
            [IssuePanelIds::RAW, IssuePanelIds::EXTRA],
            IssuePanelIds::defaultCollapsed(),
        );
    }

    public function testSanitizeKeepsAllowedUniqueStrings(): void
    {
        self::assertSame(
            [IssuePanelIds::RAW, IssuePanelIds::TAGS],
            IssuePanelIds::sanitize([
                '  raw ',
                IssuePanelIds::TAGS,
                'unknown',
                12,
                IssuePanelIds::RAW,
                null,
            ]),
        );
    }
}
