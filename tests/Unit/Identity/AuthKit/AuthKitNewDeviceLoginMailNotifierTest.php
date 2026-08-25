<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\AuthKit;

use App\Identity\AuthKit\AuthKitMailDelivery;
use App\Identity\AuthKit\AuthKitNewDeviceLoginMailNotifier;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Nowo\AuthKitBundle\DeviceIntelligence\NewDeviceLoginNotificationContext;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AuthKitNewDeviceLoginMailNotifierTest extends TestCase
{
    public function testSkipsWhenMailerUnavailable(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::never())->method('trans');

        $notifier = new AuthKitNewDeviceLoginMailNotifier($this->mailDelivery($translator, available: false));
        $notifier->notify(new NewDeviceLoginNotificationContext('user@example.com', 'default'));
    }

    public function testSkipsInvalidIdentifier(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::never())->method('trans');

        $notifier = new AuthKitNewDeviceLoginMailNotifier($this->mailDelivery($translator, available: true));
        $notifier->notify(new NewDeviceLoginNotificationContext('not-an-email', 'default'));
        $notifier->notify(new NewDeviceLoginNotificationContext('   ', 'default'));
    }

    public function testSendsWhenDeliverableAndValidEmail(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []): string => $id.'|'.($params['%email%'] ?? ''),
        );

        $notifier = new AuthKitNewDeviceLoginMailNotifier($this->mailDelivery($translator, available: true));
        $notifier->notify(new NewDeviceLoginNotificationContext('  User@Example.COM ', 'default'));

        $this->addToAssertionCount(1);
    }

    private function mailDelivery(TranslatorInterface $translator, bool $available): AuthKitMailDelivery
    {
        return new AuthKitMailDelivery($this->mailer($available), $translator);
    }

    private function mailer(bool $available): ConfiguredMailer
    {
        $settings = InstanceSettings::defaults();
        if ($available) {
            $settings->setMailerDsn('smtp://user:pass@127.0.0.1:2525');
        }

        $repository = $this->createStub(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);

        return new ConfiguredMailer($repository, new MailerDsnValidator(), 'null://null', 'test');
    }
}
