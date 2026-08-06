<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\AuthKit;

use App\Identity\AuthKit\AuthKitMagicLoginMailNotifier;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use DateTimeImmutable;
use Nowo\AuthKitBundle\MagicLogin\MagicLoginNotificationContext;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AuthKitMagicLoginMailNotifierTest extends TestCase
{
    public function testSkipsWhenMailerUnavailable(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::never())->method('trans');

        $notifier = new AuthKitMagicLoginMailNotifier($this->mailer(available: false), $translator);
        $notifier->notify($this->context('user@example.com'));
    }

    public function testSkipsInvalidIdentifier(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::never())->method('trans');

        $notifier = new AuthKitMagicLoginMailNotifier($this->mailer(available: true), $translator);
        $notifier->notify($this->context('not-an-email'));
        $notifier->notify($this->context('   '));
    }

    public function testSendsWhenDeliverableAndValidEmail(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []): string => $id.'|'.($params['%link%'] ?? ''),
        );

        $notifier = new AuthKitMagicLoginMailNotifier($this->mailer(available: true), $translator);
        $notifier->notify($this->context('  User@Example.COM '));

        $this->addToAssertionCount(1);
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

    private function context(string $identifier): MagicLoginNotificationContext
    {
        return new MagicLoginNotificationContext(
            $identifier,
            'https://beacon.test/magic',
            new DateTimeImmutable('+15 minutes'),
            'u***@example.com',
        );
    }
}
