<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Shared\Mailer\ConfiguredMailer;
use Symfony\Component\Mime\Email;

/** Production transport: encrypted instance Mailer when deliverable. */
final readonly class ConfiguredIssueUserMailTransport implements IssueUserMailTransport
{
    public function __construct(
        private ConfiguredMailer $mailer,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->mailer->isMagicLoginAvailable();
    }

    public function getFromAddress(): string
    {
        return $this->mailer->getFromAddress();
    }

    public function send(Email $email): void
    {
        $this->mailer->send($email);
    }
}
