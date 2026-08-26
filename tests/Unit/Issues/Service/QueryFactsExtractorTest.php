<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Dto\QueryFacts;
use App\Issues\Service\QueryFactsExtractor;
use PHPUnit\Framework\TestCase;

final class QueryFactsExtractorTest extends TestCase
{
    public function testParsesLaravelQueryExceptionMessage(): void
    {
        $sql = 'select `id`, `user_id`, `date`, `status` from `attendances` where `user_id` = ? group by `date`';
        $message = 'SQLSTATE[42000]: Syntax error or access violation: 1055 Expression #1 of SELECT list is not in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by (Connection: mysql, SQL: '.$sql.')';

        $facts = new QueryFactsExtractor()->extract([
            'exception' => [
                'values' => [[
                    'type' => 'Illuminate\\Database\\QueryException',
                    'value' => $message,
                ]],
            ],
        ]);

        self::assertInstanceOf(QueryFacts::class, $facts);
        self::assertSame('42000', $facts->sqlstate);
        self::assertSame('1055', $facts->vendorCode);
        self::assertSame('mysql', $facts->driver);
        self::assertSame('only_full_group_by', $facts->sqlMode);
        self::assertSame($sql, $facts->sql);
        self::assertSame(QueryFacts::SOURCE_EXCEPTION, $facts->source);
        self::assertNotNull($facts->summary);
        self::assertStringNotContainsString('(SQL:', $facts->summary);
    }

    public function testStructuredContextsDbWinsOverMessage(): void
    {
        $facts = new QueryFactsExtractor()->extract([
            'exception' => [
                'values' => [[
                    'value' => 'SQLSTATE[HY000]: General error: 1 (SQL: select 1)',
                ]],
            ],
            'contexts' => [
                'db' => [
                    'sqlstate' => '42000',
                    'code' => '1055',
                    'sql' => 'SELECT id FROM attendances GROUP BY date',
                    'driver' => 'pdo_mysql',
                ],
            ],
        ]);

        self::assertInstanceOf(QueryFacts::class, $facts);
        self::assertSame('42000', $facts->sqlstate);
        self::assertSame('1055', $facts->vendorCode);
        self::assertSame('SELECT id FROM attendances GROUP BY date', $facts->sql);
        self::assertSame(QueryFacts::SOURCE_STRUCTURED, $facts->source);
    }

    public function testUsesLastQueryBreadcrumbWhenNoSqlInMessage(): void
    {
        $facts = new QueryFactsExtractor()->extract([
            'exception' => [
                'values' => [[
                    'value' => 'SQLSTATE[HY000]: General error: 1040 Too many connections',
                ]],
            ],
            'breadcrumbs' => [
                'values' => [
                    ['category' => 'query', 'message' => 'SELECT 1', 'data' => ['sql' => 'SELECT 1']],
                    ['category' => 'db.query', 'data' => ['sql' => 'SELECT id FROM users LIMIT 1']],
                ],
            ],
        ]);

        self::assertInstanceOf(QueryFacts::class, $facts);
        self::assertSame('HY000', $facts->sqlstate);
        self::assertSame('SELECT id FROM users LIMIT 1', $facts->sql);
        self::assertSame(QueryFacts::SOURCE_BREADCRUMB, $facts->source);
    }

    public function testParsesMysqlTooManyConnectionsWithoutSqlstatePrefix(): void
    {
        $facts = new QueryFactsExtractor()->extract([
            'message' => "(1040, 'Too many connections')",
        ]);

        self::assertInstanceOf(QueryFacts::class, $facts);
        self::assertSame('1040', $facts->vendorCode);
        self::assertNull($facts->sql);
    }

    public function testReturnsNullWhenNoDatabaseFacts(): void
    {
        self::assertNull(new QueryFactsExtractor()->extract([
            'exception' => [
                'values' => [['type' => 'RuntimeException', 'value' => 'boom']],
            ],
        ]));
    }

    public function testTruncatesLongSql(): void
    {
        $sql = str_repeat('SELECT 1, ', 2000);
        $facts = new QueryFactsExtractor()->extract([
            'extra' => ['sql' => $sql],
        ]);

        self::assertInstanceOf(QueryFacts::class, $facts);
        self::assertTrue($facts->sqlTruncated);
        self::assertNotNull($facts->sql);
        self::assertSame(QueryFactsExtractor::MAX_SQL_DISPLAY, mb_strlen($facts->sql));
    }

    public function testSearchesExceptionChain(): void
    {
        $facts = new QueryFactsExtractor()->extract([
            'exception' => [
                'values' => [
                    ['type' => 'PDOException', 'value' => 'SQLSTATE[42000]: Syntax error or access violation: 1055'],
                    ['type' => 'RuntimeException', 'value' => 'wrapper (SQL: SELECT * FROM attendances)'],
                ],
            ],
        ]);

        self::assertInstanceOf(QueryFacts::class, $facts);
        self::assertSame('42000', $facts->sqlstate);
        self::assertSame('1055', $facts->vendorCode);
        self::assertSame('SELECT * FROM attendances', $facts->sql);
    }

    public function testExtraQueryBindingsSqlstateAndInvalidPayloadShapes(): void
    {
        $facts = new QueryFactsExtractor()->extract([
            'extra' => [
                'query' => 'SELECT 2',
                'bindings' => ['a'],
                'sqlstate' => 'HY000',
            ],
        ]);

        self::assertInstanceOf(QueryFacts::class, $facts);
        self::assertSame('SELECT 2', $facts->sql);
        self::assertSame(['a'], $facts->bindings);
        self::assertSame('HY000', $facts->sqlstate);

        self::assertNull(new QueryFactsExtractor()->extract([
            'breadcrumbs' => ['values' => 'not-a-list'],
        ]));

        $fromMessage = new QueryFactsExtractor()->extract([
            'breadcrumbs' => [
                'values' => [
                    ['category' => 'query', 'message' => 'SELECT name FROM users'],
                    ['category' => 'query', 'message' => 'not sql'],
                ],
            ],
        ]);
        self::assertSame('SELECT name FROM users', $fromMessage->sql);

        $chain = new QueryFactsExtractor()->extract([
            'exception' => [
                'values' => ['bad-entry', ['value' => 'SQLSTATE[42000]: Syntax error: 1055']],
            ],
            'extra' => ['sql' => 'SELECT 1'],
        ]);
        self::assertSame('42000', $chain->sqlstate);

        $longSummary = new QueryFactsExtractor()->extract([
            'exception' => [
                'values' => [['value' => str_repeat('E', 500)]],
            ],
            'extra' => ['sql' => 'SELECT 1'],
        ]);
        self::assertNotNull($longSummary->summary);
        self::assertSame(401, mb_strlen((string) $longSummary->summary));

        $missingValue = new QueryFactsExtractor()->extract([
            'exception' => [
                'values' => [['type' => 'PDOException']],
            ],
            'extra' => ['sql' => 'SELECT 1'],
        ]);
        self::assertSame('SELECT 1', $missingValue->sql);
    }
}
