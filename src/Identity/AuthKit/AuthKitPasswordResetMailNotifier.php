<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use App\Shared\Mailer\ConfiguredMailer;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetNotificationContext;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetNotifierInterface;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetTokenResult;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Delivers AuthKit password-reset emails via encrypted instance Mailer. */
final class AuthKitPasswordResetMailNotifier implements PasswordResetNotifierInterface
{
    public function __construct(
        private readonly ConfiguredMailer $mailer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function notify(PasswordResetTokenResult $token, PasswordResetNotificationContext $context): void
    {
        if (!$this->mailer->isMagicLoginAvailable()) {
            return;
        }

        $to = trim(strtolower($context->identifier));
        if ('' === $to || !filter_var($to, \FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $message = (new Email())
            ->from($this->mailer->getFromAddress())
            ->to($to)
            ->subject($this->translator->trans('auth.reset.email_subject'))
            ->text($this->translator->trans('auth.reset.email_body', [
                '%link%' => $context->resetUrl,
                '%code%' => $token->code() ?? '',
            ]));

        $this->mailer->send($message);
    }
}
