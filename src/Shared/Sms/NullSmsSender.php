<?php

declare(strict_types=1);

namespace App\Shared\Sms;

use App\Shared\Sms\Exception\SmsSendException;
use App\Shared\Sms\Provider\SmsProviderId;

/**
 * No-op / disabled SMS transport.
 */
final class NullSmsSender implements SmsSenderInterface
{
    public function send(SmsOutboundMessage $message): SmsSendResult
    {
        throw new SmsSendException('SMS provider is disabled (SMS_PROVIDER=null).');
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function getProviderId(): string
    {
        return SmsProviderId::Null->value;
    }
}
