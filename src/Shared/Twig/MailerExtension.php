<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Mailer\ConfiguredMailer;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Exposes mailer/instance capability flags to Twig (magic login gating). */
final class MailerExtension extends AbstractExtension
{
    public function __construct(
        private readonly ConfiguredMailer $mailer,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('beacon_magic_login_enabled', $this->mailer->isMagicLoginAvailable(...)),
        ];
    }
}
