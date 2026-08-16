<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Entity;

use App\Identity\Entity\User;
use App\Shared\Settings\Entity\InstanceSettings;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;

final class InstanceSettingsTest extends TestCase
{
    public function testDefaultsAndSetupCompletionLifecycle(): void
    {
        $settings = InstanceSettings::defaults();
        self::assertSame(1, $settings->getId());
        self::assertFalse($settings->isSetupCompleted());
        self::assertNull($settings->getSetupCompletedAt());

        $settings->markSetupCompleted();
        self::assertTrue($settings->isSetupCompleted());
        self::assertInstanceOf(DateTimeImmutable::class, $settings->getSetupCompletedAt());

        $settings->clearSetupCompleted();
        self::assertFalse($settings->isSetupCompleted());
    }

    public function testAuditUsersOnlyAcceptUserInstances(): void
    {
        $user = new User()->setEmail('admin@example.com');
        $settings = InstanceSettings::defaults();
        $settings->setCreatedBy($user);
        $settings->setUpdatedBy(new stdClass());

        self::assertSame($user, $settings->getCreatedBy());
        self::assertNull($settings->getUpdatedBy());
    }
}
