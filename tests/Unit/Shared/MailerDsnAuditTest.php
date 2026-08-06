<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Mailer\MailerDsnAudit;
use PHPUnit\Framework\TestCase;

final class MailerDsnAuditTest extends TestCase
{
    public function testRedactsSchemeAndHostWithoutUserinfo(): void
    {
        self::assertSame(
            ['scheme' => 'smtp', 'host' => 'mail.example'],
            MailerDsnAudit::redact('smtp://user:s3cret@mail.example:587'),
        );
        self::assertSame(['scheme' => null, 'host' => null], MailerDsnAudit::redact(null));
        self::assertSame(['scheme' => null, 'host' => null], MailerDsnAudit::redact(''));
        self::assertSame(['scheme' => 'sendmail', 'host' => 'default'], MailerDsnAudit::redact('sendmail://default'));
    }
}
