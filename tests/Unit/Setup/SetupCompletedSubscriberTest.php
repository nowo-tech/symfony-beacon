<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Setup\InstanceSettingsDurableSetupDoneStore;
use App\Setup\SetupCompletedSubscriber;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Nowo\SiteBackupBundle\Setup\Storage\SetupMarkerManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SetupCompletedSubscriberTest extends TestCase
{
    private string $tmpDir;

    private string $requiredFile;

    private string $doneFile;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/beacon-setup-completed-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->tmpDir);
        $this->requiredFile = $this->tmpDir.'/setup.required';
        $this->doneFile = $this->tmpDir.'/setup.done';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tmpDir);
    }

    public function testMarksSetupCompletedAndDoneMarkerWhenNotYetDone(): void
    {
        $settings = InstanceSettings::defaults();
        self::assertFalse($settings->isSetupCompleted());

        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);
        $repository->expects(self::once())->method('save')->with($settings);

        $markers = new SetupMarkerManager($this->requiredFile, $this->doneFile);
        $subscriber = new SetupCompletedSubscriber(
            new InstanceSettingsDurableSetupDoneStore($repository),
            $markers,
        );
        $subscriber();

        self::assertTrue($settings->isSetupCompleted());
        self::assertTrue($markers->isDone());
    }

    public function testStillHealsDoneMarkerWhenAlreadyCompleted(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->markSetupCompleted();

        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);
        $repository->expects(self::never())->method('save');

        $markers = new SetupMarkerManager($this->requiredFile, $this->doneFile);
        self::assertFalse($markers->isDone());

        $subscriber = new SetupCompletedSubscriber(
            new InstanceSettingsDurableSetupDoneStore($repository),
            $markers,
        );
        $subscriber();

        self::assertTrue($settings->isSetupCompleted());
        self::assertTrue($markers->isDone());
    }
}
