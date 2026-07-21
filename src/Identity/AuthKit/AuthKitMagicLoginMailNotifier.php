<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use App\Shared\Mailer\ConfiguredMailer;
use Nowo\AuthKitBundle\MagicLogin\MagicLoginNotificationContext;
use Nowo\AuthKitBundle\MagicLogin\MagicLoginNotifierInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Delivers AuthKit magic-login links via encrypted instance Mailer. */
final class AuthKitMagicLoginMailNotifier implements MagicLoginNotifierInterface
{
    public function __construct(
        private readonly ConfiguredMailer $mailer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function notify(MagicLoginNotificationContext $context): void
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
            ->subject($this->translator->trans('auth.magic.email_subject'))
            ->text($this->translator->trans('auth.magic.email_body', [
                '%link%' => $context->loginUrl,
                '%expires%' => $context->expiresAt->format('Y-m-d H:i:s T'),
            ]));

        $this->mailer->send($message);
    }
}
