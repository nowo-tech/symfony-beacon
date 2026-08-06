<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics;

use App\Analytics\Service\AnalyticsPeriodResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AnalyticsPeriodResolverTest extends TestCase
{
    public function testDefaultPresetIsThirtyDays(): void
    {
        $resolved = new AnalyticsPeriodResolver()->resolve(Request::create('/'));

        self::assertTrue($resolved['valid']);
        self::assertSame('30', $resolved['period']);
        self::assertNull($resolved['error']);
        self::assertSame(29, (int) $resolved['from']->diff($resolved['to'])->days);
    }

    public function testSevenDayPreset(): void
    {
        $resolved = new AnalyticsPeriodResolver()->resolve(Request::create('/?period=7'));

        self::assertTrue($resolved['valid']);
        self::assertSame('7', $resolved['period']);
        self::assertSame(6, (int) $resolved['from']->diff($resolved['to'])->days);
    }

    public function testCustomRangePreservesUtcDays(): void
    {
        $resolved = new AnalyticsPeriodResolver()->resolve(Request::create('/?period=custom&from=2026-01-01&to=2026-01-10'));

        self::assertTrue($resolved['valid']);
        self::assertSame('custom', $resolved['period']);
        self::assertSame('2026-01-01', $resolved['from']->format('Y-m-d'));
        self::assertSame('2026-01-10', $resolved['to']->format('Y-m-d'));
    }

    public function testInvalidPresetFallsBack(): void
    {
        $resolved = new AnalyticsPeriodResolver()->resolve(Request::create('/?period=nope'));

        self::assertFalse($resolved['valid']);
        self::assertSame('30', $resolved['period']);
        self::assertSame('analytics.period.invalid', $resolved['error']);
    }

    public function testQueryParamsIncludeFilters(): void
    {
        $resolver = new AnalyticsPeriodResolver();
        $resolved = $resolver->resolve(Request::create('/?period=14'));
        $query = $resolver->queryParams($resolved, 'prod', '1.2.3', 'error');

        self::assertSame([
            'period' => '14',
            'environment' => 'prod',
            'release' => '1.2.3',
            'level' => 'error',
        ], $query);
    }
}
