<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues;

use App\Issues\AssignmentScope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssignmentScopeTest extends TestCase
{
    #[DataProvider('queryProvider')]
    public function testTryFromQuery(?string $value, AssignmentScope $expected): void
    {
        self::assertSame($expected, AssignmentScope::tryFromQuery($value));
    }

    /**
     * @return iterable<string, array{?string, AssignmentScope}>
     */
    public static function queryProvider(): iterable
    {
        yield 'null' => [null, AssignmentScope::Mine];
        yield 'empty' => ['', AssignmentScope::Mine];
        yield 'mine' => ['mine', AssignmentScope::Mine];
        yield 'teammates' => ['teammates', AssignmentScope::Teammates];
        yield 'unassigned' => ['unassigned', AssignmentScope::Unassigned];
        yield 'all' => ['all', AssignmentScope::All];
        yield 'unknown' => ['other', AssignmentScope::Mine];
    }
}
