<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Setup\InstanceSettingsDurableSetupDoneStore;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InstanceSettingsDurableSetupDoneStoreTest extends TestCase
{
    public function testIsDoneAndMarkDone(): void
    {
        $settings = InstanceSettings::defaults();
        $repo = $this->createMock(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);
        $repo->expects(self::once())->method('save')->with($settings);

        $store = new InstanceSettingsDurableSetupDoneStore($repo);
        self::assertFalse($store->isDone());
        $store->markDone();
        self::assertTrue($settings->isSetupCompleted());
        self::assertTrue($store->isDone());
    }

    public function testIsDoneFalseWhenRepositoryThrows(): void
    {
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willThrowException(new RuntimeException('no schema'));

        self::assertFalse((new InstanceSettingsDurableSetupDoneStore($repo))->isDone());
    }
}
