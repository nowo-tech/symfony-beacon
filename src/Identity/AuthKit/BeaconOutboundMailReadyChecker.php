<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use App\Shared\Mailer\ConfiguredMailer;
use Nowo\AuthKitBundle\Mailer\OutboundMailReadyCheckerInterface;

/**
 * Gates AuthKit magic-login / password-reset UI on a deliverable instance mailer DSN.
 */
final readonly class BeaconOutboundMailReadyChecker implements OutboundMailReadyCheckerInterface
{
    public function __construct(
        private ConfiguredMailer $mailer,
    ) {
    }

    public function isReady(): bool
    {
        return $this->mailer->isMagicLoginAvailable();
    }
}
