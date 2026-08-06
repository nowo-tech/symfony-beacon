<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Service\HookMutationPolicy;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use PHPUnit\Framework\TestCase;

final class HookMutationPolicyTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testAnonymousResolveDisabledByDefaultConstructor(): void
    {
        self::assertFalse(new HookMutationPolicy($this->opsDefaultsWith(static function (InstanceSettings $settings): void {
            $settings->setAllowAnonymousResolve(false);
        }))->allowAnonymousResolve());
    }

    public function testAnonymousResolveCanBeEnabled(): void
    {
        self::assertTrue(new HookMutationPolicy($this->opsDefaultsWith(static function (InstanceSettings $settings): void {
            $settings->setAllowAnonymousResolve(true);
        }))->allowAnonymousResolve());
    }
}
