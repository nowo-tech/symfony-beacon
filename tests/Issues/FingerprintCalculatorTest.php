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
                            ['filename' => 'src/Foo.php', 'function' => 'bar', 'lineno' => 10, 'in_app' => true],
                        ],
                    ],
                ]],
            ],
        ];

        self::assertSame($calc->calculate($payload), $calc->calculate($payload));
        self::assertStringContainsString('RuntimeException', $calc->title($payload));
        self::assertSame('bar', $calc->culprit($payload));
    }

    public function testGroupsSimilarExceptionsIgnoringLineAndVolatileIds(): void
    {
        $calc = new FingerprintCalculator();
        $base = [
            'exception' => [
                'values' => [[
                    'type' => 'RuntimeException',
                    'value' => 'User 42 not found',
                    'stacktrace' => [
                        'frames' => [
                            ['filename' => '/app/src/User/Finder.php', 'function' => 'App\\User\\Finder::find', 'lineno' => 10, 'in_app' => true],
                        ],
                    ],
                ]],
            ],
        ];
        $similar = $base;
        $similar['exception']['values'][0]['value'] = 'User 99 not found';
        $similar['exception']['values'][0]['stacktrace']['frames'][0]['lineno'] = 99;

        self::assertSame($calc->calculate($base), $calc->calculate($similar));
    }

    public function testNormalizesMessageTokens(): void
    {
        $calc = new FingerprintCalculator();
        self::assertSame(
            'order <uuid> failed after <n> retries',
            $calc->normalizeMessage('order 550e8400-e29b-41d4-a716-446655440000 failed after 3 retries'),
        );
    }

    public function testGroupsMessagesWithSameNormalizedText(): void
    {
        $calc = new FingerprintCalculator();
        $a = $calc->calculate(['message' => 'Timeout after 12s', 'culprit' => 'demo']);
        $b = $calc->calculate(['message' => 'Timeout after 40s', 'culprit' => 'demo']);

        self::assertSame($a, $b);
    }
}
