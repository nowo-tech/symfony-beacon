<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics;

use App\Analytics\Service\AnalyticsPeriodResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AnalyticsPeriodResolverExtraTest extends TestCase
{
    public function testCustomRangeRejectsMissingInvalidAndTooLongInputs(): void
    {
        $resolver = new AnalyticsPeriodResolver();

        $required = $resolver->resolve(Request::create('/?period=custom&from=2026-01-01'));
        self::assertFalse($required['valid']);
        self::assertSame('analytics.period.custom_required', $required['error']);

        $invalid = $resolver->resolve(Request::create('/?period=custom&from=2026-02-30&to=2026-03-01'));
        self::assertFalse($invalid['valid']);
        self::assertSame('analytics.period.invalid_date', $invalid['error']);

        $tooLong = $resolver->resolve(Request::create('/?period=custom&from=2024-01-01&to=2025-12-31'));
        self::assertFalse($tooLong['valid']);
        self::assertSame('analytics.period.too_long', $tooLong['error']);
    }
}
