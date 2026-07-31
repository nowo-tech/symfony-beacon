<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Shared\Http\SafeInternalRedirect;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Guest locale helpers for public pages that support optional /{_locale} prefixes.
 *
 * Guests change language via path switcher (AuthKit/legal) or POST /locale/{locale} (CSRF).
 * Bare AuthKit URLs are handled by AuthKit (locale.in_path=both). Legal bare URLs redirect via BarePublicLocaleRedirectController.
 */
final class GuestLocaleController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
    ) {
    }

    /**
     * @return list<string>
     */
    private function enabledLocales(): array
    {
        $enabled = $this->getParameter('kernel.enabled_locales');

        return \is_array($enabled) ? array_values(array_map(strval(...), $enabled)) : [$this->defaultLocale];
    }

    #[Route(
        '/locale/{locale}',
        name: 'guest_locale_switch',
        requirements: ['locale' => 'en|es|de|nl|fr|it|pt'],
        methods: ['POST'],
    )]
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (!\in_array($locale, $this->enabledLocales(), true)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('guest_locale', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $request->getSession()->set('_locale', $locale);

        return $this->redirect($this->safeRedirectTarget($request, $locale));
    }

    private function safeRedirectTarget(Request $request, string $locale): string
    {
        $fallback = $locale === $this->defaultLocale
            ? $this->generateUrl('nowo_auth_kit_login_unlocalized')
            : $this->generateUrl('nowo_auth_kit_login', ['_locale' => $locale]);
        $redirect = (string) $request->request->get('redirect', $request->query->get('redirect', ''));
        $safe = SafeInternalRedirect::resolve($request, $redirect, $fallback);
        if ($safe === $fallback) {
            return $fallback;
        }

        // Prefer keeping guests on a locale-prefixed public URL when switching language.
        $localized = $this->localizePublicPath($safe, $locale);

        return $localized ?? $safe;
    }

    private function localizePublicPath(string $path, string $locale): ?string
    {
        $pathOnly = parse_url($path, \PHP_URL_PATH);
        if (!\is_string($pathOnly) || '' === $pathOnly) {
            return null;
        }

        $query = parse_url($path, \PHP_URL_QUERY);
        $suffix = \is_string($query) && '' !== $query ? '?'.$query : '';

        $rest = $pathOnly;
        if (preg_match('#^/(en|es|de|nl|fr|it|pt)(/.*)?$#', $pathOnly, $m)) {
            $rest = $m[2] ?? '';
            if ('' === $rest) {
                $rest = '/';
            }
        }

        $publicPrefixes = [
            '/login',
            '/register',
            '/logout',
            '/reset-password',
            '/legal',
            '/setup',
        ];
        $isPublic = false;
        foreach ($publicPrefixes as $prefix) {
            if ($rest === $prefix || str_starts_with($rest, $prefix.'/')) {
                $isPublic = true;
                break;
            }
        }
        if (!$isPublic && $pathOnly === $rest) {
            // Bare public path without locale prefix.
            foreach ($publicPrefixes as $prefix) {
                if ($pathOnly === $prefix || str_starts_with($pathOnly, $prefix.'/')) {
                    $isPublic = true;
                    $rest = $pathOnly;
                    break;
                }
            }
        }
        if (!$isPublic) {
            return null;
        }

        // Default locale stays unprefixed for AuthKit + setup dual URLs; others use /{locale}/….
        if ($locale === $this->defaultLocale) {
            return $rest.$suffix;
        }

        return '/'.$locale.$rest.$suffix;
    }
}
