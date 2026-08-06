<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Mailer\MailerDsnValidator;
use PHPUnit\Framework\TestCase;

final class MailerDsnValidatorTest extends TestCase
{
    public function testAcceptsSmtpAndRejectsNullOrGarbage(): void
    {
        $validator = new MailerDsnValidator();

        self::assertNull($validator->validatePlainDsn(''));
        self::assertNull($validator->validatePlainDsn('smtp://user:pass@mail.example:587'));
        self::assertTrue($validator->isDeliverable('smtp://user:pass@mail.example:587'));

        self::assertSame('instance_mailer.mailer_dsn.null_transport', $validator->validatePlainDsn('null://null'));
        self::assertFalse($validator->isDeliverable('null://null'));

        self::assertSame('instance_mailer.mailer_dsn.scheme_not_allowed', $validator->validatePlainDsn('smtp://'));
        self::assertFalse($validator->isDeliverable('smtp://'));

        self::assertSame('instance_mailer.mailer_dsn.invalid', $validator->validatePlainDsn('smtp://exa mple.com'));
        self::assertFalse($validator->isDeliverable('smtp://exa mple.com'));

        self::assertSame('instance_mailer.mailer_dsn.scheme_not_allowed', $validator->validatePlainDsn('not-a-dsn'));
        self::assertFalse($validator->isDeliverable('not-a-dsn'));
    }

    public function testRejectsDisallowedSchemesEvenIfParseableShape(): void
    {
        $validator = new MailerDsnValidator();

        self::assertSame(
            'instance_mailer.mailer_dsn.scheme_not_allowed',
            $validator->validatePlainDsn('file:///tmp/mail.sock'),
        );
        self::assertFalse($validator->isDeliverable('file:///tmp/mail.sock'));
    }

    public function testRejectsSendmailAndNativeSchemes(): void
    {
        $validator = new MailerDsnValidator();

        self::assertSame(
            'instance_mailer.mailer_dsn.scheme_not_allowed',
            $validator->validatePlainDsn('sendmail://default'),
        );
        self::assertSame(
            'instance_mailer.mailer_dsn.scheme_not_allowed',
            $validator->validatePlainDsn('sendmail://default?command=/usr/bin/env%20id%20-t'),
        );
        self::assertSame(
            'instance_mailer.mailer_dsn.scheme_not_allowed',
            $validator->validatePlainDsn('native://default'),
        );
        self::assertFalse($validator->isDeliverable('sendmail://default'));
        self::assertFalse($validator->isDeliverable('native://default'));
    }
}
