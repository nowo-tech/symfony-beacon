<?php

declare(strict_types=1);

namespace App\Tests\Shared;

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

        self::assertSame('instance_mailer.mailer_dsn.invalid', $validator->validatePlainDsn('not-a-dsn'));
        self::assertFalse($validator->isDeliverable('not-a-dsn'));
    }
}
