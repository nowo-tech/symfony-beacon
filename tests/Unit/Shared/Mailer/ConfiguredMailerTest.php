<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Mailer;

use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ConfiguredMailerTest extends TestCase
{
    public function testEffectiveDsnFallsBackToEnvAndNull(): void
    {
        $settings = InstanceSettings::defaults();
        $mailer = $this->mailer($settings, 'smtp://env@example:25');
        self::assertSame('smtp://env@example:25', $mailer->getEffectiveDsn());
        self::assertFalse($mailer->isConfiguredFromDatabase());
        self::assertFalse($mailer->isMagicLoginAvailable());

        $emptyEnv = $this->mailer($settings, '');
        self::assertSame('null://null', $emptyEnv->getEffectiveDsn());
    }

    public function testDatabaseDsnEnablesMagicLoginWhenDeliverable(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMailerDsn('smtp://user:pass@127.0.0.1:2525');
        $settings->setMailerFrom('beacon@example.com');
        $mailer = $this->mailer($settings, 'null://null');

        self::assertTrue($mailer->isConfiguredFromDatabase());
        self::assertTrue($mailer->isMagicLoginAvailable());
        self::assertSame('beacon@example.com', $mailer->getFromAddress());
        self::assertSame('smtp://user:pass@127.0.0.1:2525', $mailer->getEffectiveDsn());
    }

    public function testSendSampleRequiresMagicLoginAndUsesNullTransportInTest(): void
    {
        $settings = InstanceSettings::defaults();
        $mailer = $this->mailer($settings, 'null://null');
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Sample');

        try {
            $mailer->sendSample('to@example.com', $translator);
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('encrypted non-null', $e->getMessage());
        }

        $settings->setMailerDsn('smtp://user:pass@127.0.0.1:2525');
        $settings->setMailerFrom('from@example.com');
        $mailer = $this->mailer($settings, 'null://null', 'test');
        $mailer->sendSample('to@example.com', $translator);
        $mailer->reset();
        $mailer->send(new Email()->from('from@example.com')->to('to@example.com')->subject('x')->text('y'));
        self::assertTrue(true);
    }

    private function mailer(InstanceSettings $settings, string $envDsn, string $env = 'test'): ConfiguredMailer
    {
        $repository = $this->createStub(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);

        return new ConfiguredMailer($repository, new MailerDsnValidator(), $envDsn, $env);
    }
}
