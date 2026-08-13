<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Ops\Metrics\PrometheusTextFormatter;
use PHPUnit\Framework\TestCase;

final class PrometheusTextFormatterTest extends TestCase
{
    public function testFormatEscapesLabelsAndSpecialValues(): void
    {
        $text = new PrometheusTextFormatter()->format([
            [
                'name' => 'beacon_demo',
                'type' => 'gauge',
                'help' => 'Demo metric',
                'samples' => [
                    ['labels' => [], 'value' => 1.5],
                    ['labels' => ['reason' => "a\"b\\c\nd"], 'value' => \NAN],
                    ['labels' => ['sign' => 'pos'], 'value' => \INF],
                    ['labels' => ['sign' => 'neg'], 'value' => -\INF],
                    ['labels' => ['z' => 'zero'], 'value' => 0.0],
                ],
            ],
        ]);

        self::assertStringContainsString("# HELP beacon_demo Demo metric\n", $text);
        self::assertStringContainsString("# TYPE beacon_demo gauge\n", $text);
        self::assertStringContainsString("beacon_demo 1.5\n", $text);
        self::assertStringContainsString('beacon_demo{reason="a\\"b\\\\c\\nd"} NaN', $text);
        self::assertStringContainsString('beacon_demo{sign="pos"} +Inf', $text);
        self::assertStringContainsString('beacon_demo{sign="neg"} -Inf', $text);
        self::assertStringContainsString('beacon_demo{z="zero"} 0', $text);
        self::assertStringEndsWith("\n", $text);
    }
}
