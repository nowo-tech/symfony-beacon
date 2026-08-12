<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Entity;

use App\Notifications\Entity\ProjectThresholdRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProjectThresholdRuleTest extends TestCase
{
    public function testNormalizeHelpersTrimAndNullEmpty(): void
    {
        self::assertNull(ProjectThresholdRule::normalizeEnvironment('  '));
        self::assertNull(ProjectThresholdRule::normalizeRelease(null));
        self::assertSame('prod', ProjectThresholdRule::normalizeEnvironment(' prod '));
        self::assertSame('1.2.3', ProjectThresholdRule::normalizeRelease('1.2.3'));
    }

    public function testCooldownAndMarkFired(): void
    {
        $rule = new ProjectThresholdRule();
        $rule->setErrorCount(5);
        $rule->setWindowMinutes(30);
        $rule->setCooldownMinutes(60);
        $now = new DateTimeImmutable('2026-08-12T12:00:00+00:00');

        self::assertFalse($rule->isCooldownActive($now));
        $rule->markFired($now);
        self::assertTrue($rule->isCooldownActive($now->modify('+30 minutes')));
        self::assertFalse($rule->isCooldownActive($now->modify('+61 minutes')));
        self::assertSame(5, $rule->getErrorCount());
        self::assertSame(30, $rule->getWindowMinutes());
    }

    public function testEnvironmentAndReleaseSettersNormalize(): void
    {
        $rule = new ProjectThresholdRule();
        $rule->setEnvironment('  staging ');
        $rule->setReleaseVersion('  ');
        self::assertSame('staging', $rule->getEnvironment());
        self::assertNull($rule->getReleaseVersion());
    }
}
