<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Entity\ProjectThresholdRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProjectThresholdRuleUnitTest extends TestCase
{
    public function testNormalizeEnvironmentAndRelease(): void
    {
        self::assertNull(ProjectThresholdRule::normalizeEnvironment(null));
        self::assertNull(ProjectThresholdRule::normalizeEnvironment('  '));
        self::assertSame('prod', ProjectThresholdRule::normalizeEnvironment(' prod '));
        self::assertSame(80, \strlen((string) ProjectThresholdRule::normalizeEnvironment(str_repeat('e', 100))));

        self::assertNull(ProjectThresholdRule::normalizeRelease(''));
        self::assertSame('1.2.3', ProjectThresholdRule::normalizeRelease(' 1.2.3 '));
        self::assertSame(120, \strlen((string) ProjectThresholdRule::normalizeRelease(str_repeat('r', 200))));
    }

    public function testSettersClampAndCooldown(): void
    {
        $rule = new ProjectThresholdRule();
        $rule->setErrorCount(0);
        $rule->setWindowMinutes(9999);
        $rule->setCooldownMinutes(0);
        $rule->setLabel('  ');
        $rule->setEnvironment(' staging ');
        $rule->setReleaseVersion(' v1 ');

        self::assertSame(1, $rule->getErrorCount());
        self::assertSame(1440, $rule->getWindowMinutes());
        self::assertSame(1, $rule->getCooldownMinutes());
        self::assertNull($rule->getLabel());
        self::assertSame('staging', $rule->getEnvironment());
        self::assertSame('v1', $rule->getReleaseVersion());

        $now = new DateTimeImmutable('2026-08-06 12:00:00');
        self::assertFalse($rule->isCooldownActive($now));

        $rule->setCooldownMinutes(60);
        $rule->markFired($now->modify('-30 minutes'));
        self::assertTrue($rule->isCooldownActive($now));

        $rule->markFired($now->modify('-90 minutes'));
        self::assertFalse($rule->isCooldownActive($now));
    }
}
