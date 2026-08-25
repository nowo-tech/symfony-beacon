<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use Nowo\AuthKitBundle\DeviceIntelligence\NewDeviceLoginNotificationContext;
use Nowo\AuthKitBundle\DeviceIntelligence\NewDeviceLoginNotifierInterface;

/** Delivers AuthKit “new browser signed in” notices via encrypted instance Mailer. */
final readonly class AuthKitNewDeviceLoginMailNotifier implements NewDeviceLoginNotifierInterface
{
    public function __construct(
        private AuthKitMailDelivery $mailDelivery,
    ) {
    }

    public function notify(NewDeviceLoginNotificationContext $context): void
    {
        $this->mailDelivery->send(
            $context->userIdentifier,
            'auth.magic.new_device_email_subject',
            'auth.magic.new_device_email_body',
            [
                '%email%' => $context->userIdentifier,
            ],
        );
    }
}
