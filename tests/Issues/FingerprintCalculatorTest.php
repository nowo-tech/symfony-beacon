<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Issues\Service\FingerprintCalculator;
use PHPUnit\Framework\TestCase;

final class FingerprintCalculatorTest extends TestCase
{
    public function testUsesExplicitFingerprint(): void
    {
        $calc = new FingerprintCalculator();
        $a = $calc->calculate(['fingerprint' => ['custom', 'a']]);
        $b = $calc->calculate(['fingerprint' => ['custom', 'a']]);
        $c = $calc->calculate(['fingerprint' => ['custom', 'b']]);

        self::assertSame($a, $b);
        self::assertNotSame($a, $c);
    }

    public function testGroupsSameExceptionFrames(): void
    {
        $calc = new FingerprintCalculator();
        $payload = [
            'exception' => [
                'values' => [[
                    'type' => 'RuntimeException',
                    'value' => 'fail',
                    'stacktrace' => [
                        'frames' => [
                            ['filename' => 'src/Foo.php', 'function' => 'bar', 'lineno' => 10],
                        ],
                    ],
                ]],
            ],
        ];

        self::assertSame($calc->calculate($payload), $calc->calculate($payload));
        self::assertStringContainsString('RuntimeException', $calc->title($payload));
        self::assertSame('bar', $calc->culprit($payload));
    }
}
