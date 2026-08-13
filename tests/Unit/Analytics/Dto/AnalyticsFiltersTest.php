<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics\Dto;

use App\Analytics\Dto\AnalyticsFilters;
use PHPUnit\Framework\TestCase;

final class AnalyticsFiltersTest extends TestCase
{
    public function testFromRequestQueryConvertsEmptyToNull(): void
    {
        $filters = AnalyticsFilters::fromRequestQuery('', '', '');

        self::assertNull($filters->environment);
        self::assertNull($filters->release);
        self::assertNull($filters->level);
        self::assertSame(
            [
                'environment' => '',
                'release' => '',
                'level' => '',
            ],
            $filters->formData(),
        );
    }

    public function testFromRequestQueryKeepsNonEmptyValues(): void
    {
        $filters = AnalyticsFilters::fromRequestQuery('prod', '1.2.3', 'error');

        self::assertSame('prod', $filters->environment);
        self::assertSame('1.2.3', $filters->release);
        self::assertSame('error', $filters->level);
        self::assertSame(
            [
                'environment' => 'prod',
                'release' => '1.2.3',
                'level' => 'error',
            ],
            $filters->formData(),
        );
    }
}
