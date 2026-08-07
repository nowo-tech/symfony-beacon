<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use Nowo\AuthKitBundle\MagicLogin\MagicLoginNotificationContext;
use Nowo\AuthKitBundle\MagicLogin\MagicLoginNotifierInterface;

/** Delivers AuthKit magic-login links via encrypted instance Mailer. */
final readonly class AuthKitMagicLoginMailNotifier implements MagicLoginNotifierInterface
{
    public function __construct(
        private AuthKitMailDelivery $mailDelivery,
    ) {
    }

    public function notify(MagicLoginNotificationContext $context): void
    {
        $this->mailDelivery->send(
            $context->identifier,
            'auth.magic.email_subject',
            'auth.magic.email_body',
            [
                '%link%' => $context->loginUrl,
                '%expires%' => $context->expiresAt->format('Y-m-d H:i:s T'),
            ],
        );
    }
}
