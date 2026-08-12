<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Stable Administration entry for cookie-consent Web UI (kit routes need configId).
 */
#[IsGranted('ROLE_ADMIN')]
final class CookieConsentAdminEntryController extends AbstractController
{
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
    ) {
    }

    #[Route('/admin/cookie-consent', name: 'admin_cookie_consent', methods: ['GET'])]
    public function __invoke(): RedirectResponse
    {
        $config = $this->configRepository->findDefaultEnabled();
        if (!$config instanceof CookieConsentConfig) {
            $this->addFlash(
                'warning',
                'Cookie consent profile is missing. Run platform seed (Setup → platform or app:seed-platform).',
            );

            return $this->redirectToRoute('admin_hub');
        }

        $configId = $config->getId();
        if (null === $configId) {
            $this->addFlash(
                'warning',
                'Cookie consent profile is missing. Run platform seed (Setup → platform or app:seed-platform).',
            );

            return $this->redirectToRoute('admin_hub');
        }

        return $this->redirectToRoute('nowo_cookie_consent_config_settings_edit', [
            'configId' => $configId,
        ]);
    }
}
