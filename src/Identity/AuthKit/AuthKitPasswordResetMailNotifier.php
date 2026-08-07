<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use Nowo\AuthKitBundle\PasswordReset\PasswordResetNotificationContext;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetNotifierInterface;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetTokenResult;

/** Delivers AuthKit password-reset emails via encrypted instance Mailer. */
final readonly class AuthKitPasswordResetMailNotifier implements PasswordResetNotifierInterface
{
    public function __construct(
        private AuthKitMailDelivery $mailDelivery,
    ) {
    }

    public function notify(PasswordResetTokenResult $token, PasswordResetNotificationContext $context): void
    {
        $this->mailDelivery->send(
            $context->identifier,
            'auth.reset.email_subject',
            'auth.reset.email_body',
            [
                '%link%' => $context->resetUrl,
                '%code%' => $token->code() ?? '',
            ],
        );
    }
}
