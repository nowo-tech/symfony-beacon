<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Twig\MailerExtension;
use PHPUnit\Framework\TestCase;

final class MailerExtensionTest extends TestCase
{
    public function testExposesMagicLoginFunctionBoundToMailer(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMailerDsn('smtp://user:pass@127.0.0.1:2525');
        $repository = $this->createStub(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);
        $mailer = new ConfiguredMailer($repository, new MailerDsnValidator(), 'null://null', 'test');

        $extension = new MailerExtension($mailer);
        $functions = $extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('beacon_magic_login_enabled', $functions[0]->getName());
        $callable = $functions[0]->getCallable();
        self::assertIsCallable($callable);
        self::assertTrue($callable());
    }
}
