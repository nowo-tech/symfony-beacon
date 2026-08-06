<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Setup\SetupCompletedSubscriber;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;

final class SetupCompletedSubscriberTest extends TestCase
{
    public function testMarksSetupCompletedWhenNotYetDone(): void
    {
        $settings = InstanceSettings::defaults();
        self::assertFalse($settings->isSetupCompleted());

        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);
        $repository->expects(self::once())->method('save')->with($settings);

        $subscriber = new SetupCompletedSubscriber($repository);
        $subscriber();

        self::assertTrue($settings->isSetupCompleted());
    }

    public function testSkipsSaveWhenAlreadyCompleted(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->markSetupCompleted();

        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);
        $repository->expects(self::never())->method('save');

        $subscriber = new SetupCompletedSubscriber($repository);
        $subscriber();

        self::assertTrue($settings->isSetupCompleted());
    }
}
