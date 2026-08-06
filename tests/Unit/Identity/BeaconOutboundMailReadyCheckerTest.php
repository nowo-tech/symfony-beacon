<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\AuthKit\BeaconOutboundMailReadyChecker;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;

final class BeaconOutboundMailReadyCheckerTest extends TestCase
{
    public function testReadyWhenDeliverableDatabaseDsnPresent(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMailerDsn('smtp://user:pass@127.0.0.1:2525');

        $repository = $this->createStub(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);

        $mailer = new ConfiguredMailer($repository, new MailerDsnValidator(), 'null://null', 'test');
        $checker = new BeaconOutboundMailReadyChecker($mailer);

        self::assertTrue($checker->isReady());
    }

    public function testNotReadyWhenOnlyNullEnvFallback(): void
    {
        $settings = InstanceSettings::defaults();
        $repository = $this->createStub(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);

        $mailer = new ConfiguredMailer($repository, new MailerDsnValidator(), 'null://null', 'test');
        $checker = new BeaconOutboundMailReadyChecker($mailer);

        self::assertFalse($checker->isReady());
    }
}
