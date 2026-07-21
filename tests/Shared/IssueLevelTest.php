<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\IssueLevel;
use PHPUnit\Framework\TestCase;

final class IssueLevelTest extends TestCase
{
    public function testNormalizeMapsKnownValues(): void
    {
        self::assertSame(IssueLevel::Fatal, IssueLevel::normalize('FATAL'));
        self::assertSame(IssueLevel::Warning, IssueLevel::normalize(' warning '));
        self::assertSame(IssueLevel::Debug, IssueLevel::normalize('debug'));
    }

    public function testNormalizeFallsBackToError(): void
    {
        self::assertSame(IssueLevel::Error, IssueLevel::normalize('critical'));
        self::assertSame(IssueLevel::Error, IssueLevel::normalize(''));
    }

    public function testValuesMatchUiFilterList(): void
    {
        self::assertSame(['fatal', 'error', 'warning', 'info', 'debug'], IssueLevel::values());
    }
}
