<?php

declare(strict_types=1);

namespace App\Shared\Locale;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bare redirects for non-AuthKit public URLs (legal + home).
 * AuthKit bare auth routes are registered by the bundle when locale.in_path=both.
 */
final class BarePublicLocaleRedirectController extends AbstractController
{
    public function __construct(
        private readonly string $defaultLocale,
    ) {
    }

    #[Route('/legal/notice', name: 'legal_notice_bare_redirect', methods: ['GET'])]
    public function legalNotice(): RedirectResponse
    {
        return $this->redirectToRoute('legal_notice', ['_locale' => $this->defaultLocale]);
    }

    #[Route('/legal/privacy', name: 'legal_privacy_bare_redirect', methods: ['GET'])]
    public function legalPrivacy(): RedirectResponse
    {
        return $this->redirectToRoute('legal_privacy', ['_locale' => $this->defaultLocale]);
    }

    #[Route('/legal/terms', name: 'legal_terms_bare_redirect', methods: ['GET'])]
    public function legalTerms(): RedirectResponse
    {
        return $this->redirectToRoute('legal_terms', ['_locale' => $this->defaultLocale]);
    }

    #[Route('/legal/cookies', name: 'legal_cookies_bare_redirect', methods: ['GET'])]
    public function legalCookies(): RedirectResponse
    {
        return $this->redirectToRoute('legal_cookies', ['_locale' => $this->defaultLocale]);
    }

    #[Route('/', name: 'app_home_redirect', methods: ['GET'])]
    public function home(): RedirectResponse
    {
        return $this->redirectToRoute('nowo_auth_kit_login', ['_locale' => $this->defaultLocale]);
    }
}
