<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\AuthKit;

use App\Identity\AuthKit\AuthKitMailDelivery;
use App\Identity\AuthKit\AuthKitPasswordResetMailNotifier;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use DateTimeImmutable;
use Nowo\AuthKitBundle\Enum\PasswordResetDeliveryMode;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetNotificationContext;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetTokenResult;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AuthKitPasswordResetMailNotifierTest extends TestCase
{
    public function testSkipsWhenMailerUnavailable(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::never())->method('trans');

        $notifier = new AuthKitPasswordResetMailNotifier($this->mailDelivery($translator, available: false));
        $notifier->notify($this->token(PasswordResetDeliveryMode::Link), $this->context('user@example.com'));
    }

    public function testSkipsInvalidIdentifier(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::never())->method('trans');

        $notifier = new AuthKitPasswordResetMailNotifier($this->mailDelivery($translator, available: true));
        $notifier->notify($this->token(PasswordResetDeliveryMode::Link), $this->context('bad'));
    }

    public function testSendsWithCodeWhenDeliveryIncludesCode(): void
    {
        $seenCode = null;
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $params = []) use (&$seenCode): string {
                if (isset($params['%code%'])) {
                    $seenCode = $params['%code%'];
                }

                return $id;
            },
        );

        $notifier = new AuthKitPasswordResetMailNotifier($this->mailDelivery($translator, available: true));
        $notifier->notify(
            $this->token(PasswordResetDeliveryMode::Both, 'linktok:123456'),
            $this->context('reset@example.com'),
        );

        self::assertSame('123456', $seenCode);
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

    private function token(
        PasswordResetDeliveryMode $mode,
        string $plain = 'plain-token',
    ): PasswordResetTokenResult {
        return new PasswordResetTokenResult(
            new stdClass(),
            $plain,
            new DateTimeImmutable('+1 hour'),
            $mode,
        );
    }

    private function context(string $identifier): PasswordResetNotificationContext
    {
        return new PasswordResetNotificationContext(
            $identifier,
            'https://beacon.test/reset',
            PasswordResetDeliveryMode::Link,
            'r***@example.com',
        );
    }
}
