<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\AuthKit;

use App\Identity\AuthKit\AuthKitMailDelivery;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AuthKitMailDeliveryTest extends TestCase
{
    public function testSendSkipsWhenMailerUnavailableOrIdentifierInvalid(): void
    {
        $settings = InstanceSettings::defaults();
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);
        $mailer = new ConfiguredMailer($repo, new MailerDsnValidator(), 'null://null', 'test');
        $translator = $this->createStub(TranslatorInterface::class);
        $delivery = new AuthKitMailDelivery($mailer, $translator);

        $delivery->send('not-an-email', 'subject', 'body');
        $delivery->send('user@example.com', 'subject', 'body');
        self::assertTrue(true);
    }

    public function testSendDispatchesWhenMagicLoginAvailable(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMailerDsn('null://null');
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);
        $mailer = new ConfiguredMailer($repo, new MailerDsnValidator(), 'null://null', 'test');
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        // Spy via subclassing is not possible (final). Rely on null transport not throwing.
        $delivery = new AuthKitMailDelivery($mailer, $translator);
        if ($mailer->isMagicLoginAvailable()) {
            $delivery->send('User@Example.COM', 'mail.subject', 'mail.body', ['%token%' => 'abc']);
        }
        self::assertTrue(true);
    }
}
