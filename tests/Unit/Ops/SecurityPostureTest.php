<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ops;

use App\Ops\Service\SecurityPosture;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use PHPUnit\Framework\TestCase;

final class SecurityPostureTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testEmptyWhenSecureDefaults(): void
    {
        $posture = new SecurityPosture($this->opsDefaultsWith(static function ($settings): void {
            $settings->setAllowPrivateUrls(false);
            $settings->setAllowAnonymousResolve(false);
            $settings->setMetricsRequireToken(true);
        }));

        self::assertFalse($posture->isWeakened());
        self::assertSame([], $posture->weakenedItems());
    }

    public function testListsWeakenedFlags(): void
    {
        $posture = new SecurityPosture($this->opsDefaultsWith(static function ($settings): void {
            $settings->setAllowPrivateUrls(true);
            $settings->setAllowAnonymousResolve(true);
            $settings->setMetricsRequireToken(false);
        }));

        self::assertTrue($posture->isWeakened());
        $ids = array_column($posture->weakenedItems(), 'id');
        self::assertSame([
            'allow_private_urls',
            'allow_anonymous_resolve',
            'metrics_require_token_off',
        ], $ids);
    }
}
