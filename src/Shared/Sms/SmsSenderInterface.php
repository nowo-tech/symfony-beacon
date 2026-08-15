<?php

declare(strict_types=1);

namespace App\Shared\Sms;

/**
 * Instance-wide SMS delivery (AuthKit OTP Later, ops test sends, …).
 */
interface SmsSenderInterface
{
    public function send(SmsOutboundMessage $message): SmsSendResult;

    public function isConfigured(): bool;

    /**
     * Active provider id (`sms_bridge`, `null`, …).
     */
    public function getProviderId(): string;
}
