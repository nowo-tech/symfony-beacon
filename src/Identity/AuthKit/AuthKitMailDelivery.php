<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use App\Shared\Mailer\ConfiguredMailer;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Shared AuthKit transactional mail delivery via encrypted instance Mailer. */
final readonly class AuthKitMailDelivery
{
    public function __construct(
        private ConfiguredMailer $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, string> $bodyParams
     */
    public function send(string $identifier, string $subjectKey, string $bodyKey, array $bodyParams = []): void
    {
        if (!$this->mailer->isMagicLoginAvailable()) {
            return;
        }

        $to = trim(strtolower($identifier));
        if ('' === $to || !filter_var($to, \FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $message = new Email()
            ->from($this->mailer->getFromAddress())
            ->to($to)
            ->subject($this->translator->trans($subjectKey))
            ->text($this->translator->trans($bodyKey, $bodyParams));

        $this->mailer->send($message);
    }
}
