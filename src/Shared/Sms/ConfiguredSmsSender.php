<?php

declare(strict_types=1);

namespace App\Shared\Sms;

use App\Shared\Sms\Exception\SmsSendException;
use App\Shared\Sms\Provider\SmsBridgeProvider;
use App\Shared\Sms\Provider\SmsProviderId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves the active SMS provider from env (`SMS_PROVIDER`).
 */
final readonly class ConfiguredSmsSender implements SmsSenderInterface
{
    private SmsSenderInterface $delegate;

    public function __construct(
        SmsBridgeProvider $smsBridge,
        NullSmsSender $nullSender,
        #[Autowire('%env(SMS_PROVIDER)%')]
        string $provider = 'null',
    ) {
        $this->delegate = match (SmsProviderId::fromEnv($provider)) {
            SmsProviderId::SmsBridge => $smsBridge,
            SmsProviderId::Null => $nullSender,
        };
    }

    public function send(SmsOutboundMessage $message): SmsSendResult
    {
        if (!$this->delegate->isConfigured()) {
            throw new SmsSendException(\sprintf('SMS provider "%s" is selected but not fully configured.', $this->delegate->getProviderId()));
        }

        return $this->delegate->send($message);
    }

    public function isConfigured(): bool
    {
        return $this->delegate->isConfigured();
    }

    public function getProviderId(): string
    {
        return $this->delegate->getProviderId();
    }
}
