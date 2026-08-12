<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Doctrine;

use App\Shared\Doctrine\SqlLikeEscaper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlLikeEscaperTest extends TestCase
{
    #[DataProvider('escapeCases')]
    public function testEscape(string $input, string $expected): void
    {
        self::assertSame($expected, SqlLikeEscaper::escape($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function escapeCases(): iterable
    {
        yield 'empty' => ['', ''];
        yield 'plain' => ['hello', 'hello'];
        yield 'percent' => ['100%', '100\\%'];
        yield 'underscore' => ['a_b', 'a\\_b'];
        yield 'backslash' => ['a\\b', 'a\\\\b'];
        yield 'all' => ['\\%_', '\\\\\\%\\_'];
    }
}
