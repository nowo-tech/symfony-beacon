<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Service\ConfiguredIssueUserMailTransport;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

final class ConfiguredIssueUserMailTransportTest extends TestCase
{
    public function testDelegatesAvailabilityAndFromAddress(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMailerDsn('smtp://user:pass@127.0.0.1:2525');
        $settings->setMailerFrom('beacon@example.com');

        $transport = new ConfiguredIssueUserMailTransport($this->mailer($settings));

        self::assertTrue($transport->isAvailable());
        self::assertSame('beacon@example.com', $transport->getFromAddress());
    }

    public function testUnavailableWhenMailerNotDeliverable(): void
    {
        $transport = new ConfiguredIssueUserMailTransport($this->mailer(InstanceSettings::defaults()));

        self::assertFalse($transport->isAvailable());
    }

    public function testSendDelegatesToConfiguredMailer(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMailerDsn('smtp://user:pass@127.0.0.1:2525');

        $email = new Email()
            ->from('beacon@example.com')
            ->to('user@example.com')
            ->subject('Hi')
            ->text('body');

        new ConfiguredIssueUserMailTransport($this->mailer($settings))->send($email);

        $this->addToAssertionCount(1);
    }

    private function mailer(InstanceSettings $settings): ConfiguredMailer
    {
        $repository = $this->createStub(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);

        return new ConfiguredMailer($repository, new MailerDsnValidator(), 'null://null', 'test');
    }
}
