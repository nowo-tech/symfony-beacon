<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Entity;

use App\Notifications\Entity\ProjectThresholdRule;
use PHPUnit\Framework\TestCase;

final class ProjectThresholdRuleExtraTest extends TestCase
{
    public function testLabelAndLastFiredNormalizationPaths(): void
    {
        $rule = new ProjectThresholdRule();
        $rule->setLabel(null);
        self::assertNull($rule->getLabel());

        $rule->setLabel('   ');
        self::assertNull($rule->getLabel());

        $firedAt = new \DateTimeImmutable('2026-08-16T00:00:00+00:00');
        $rule->setLastFiredAt($firedAt);
        self::assertSame($firedAt, $rule->getLastFiredAt());
    }
}
