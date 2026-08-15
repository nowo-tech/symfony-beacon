<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Read\Dto;

use App\Api\Read\Dto\ProjectIssuesListQuery;
use PHPUnit\Framework\TestCase;

final class ProjectIssuesListQueryTest extends TestCase
{
    public function testDefaultsAndOverrides(): void
    {
        $defaults = new ProjectIssuesListQuery();
        self::assertSame(100, $defaults->limit);
        self::assertNull($defaults->q);

        $query = new ProjectIssuesListQuery(
            limit: 25,
            q: 'oom',
            level: 'fatal',
            status: 'unresolved',
            environment: 'prod',
            release: '2.0.0',
        );

        self::assertSame(25, $query->limit);
        self::assertSame('oom', $query->q);
        self::assertSame('fatal', $query->level);
        self::assertSame('unresolved', $query->status);
        self::assertSame('prod', $query->environment);
        self::assertSame('2.0.0', $query->release);
    }
}
