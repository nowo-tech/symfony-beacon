<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Settings\Service\SetupWizardAccess;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SetupWizardExtension extends AbstractExtension
{
    public function __construct(
        private readonly SetupWizardAccess $setupWizardAccess,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('beacon_setup_bootstrap_open', $this->setupWizardAccess->isBootstrapOpen(...)),
        ];
    }
}
