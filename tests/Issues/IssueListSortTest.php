<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Issues\IssueListSort;
use PHPUnit\Framework\TestCase;

final class IssueListSortTest extends TestCase
{
    public function testFromQueryFallsBackToDefaults(): void
    {
        $sort = IssueListSort::fromQuery(null, null);
        self::assertSame('last_seen', $sort->field);
        self::assertSame('desc', $sort->direction);
    }

    public function testFromQueryIgnoresUnknownField(): void
    {
        $sort = IssueListSort::fromQuery('nope', 'asc');
        self::assertSame('last_seen', $sort->field);
        self::assertSame('asc', $sort->direction);
    }

    public function testColumnClickTogglesSameField(): void
    {
        $sort = new IssueListSort('title', 'asc');
        $next = $sort->forColumnClick('title');
        self::assertSame('title', $next->field);
        self::assertSame('desc', $next->direction);
    }

    public function testColumnClickUsesDefaultDirectionForNewField(): void
    {
        $sort = new IssueListSort('title', 'asc');
        $next = $sort->forColumnClick('events');
        self::assertSame('events', $next->field);
        self::assertSame('desc', $next->direction);
    }
}
