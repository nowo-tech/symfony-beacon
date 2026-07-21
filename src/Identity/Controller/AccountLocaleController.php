<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Switches UI locale for the authenticated user (stored on the account, not in the URL).
 */
#[IsGranted('ROLE_USER')]
final class AccountLocaleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Persist preferred locale and redirect back (strips `_locale` query params).
     *
     * @param string $locale Enabled locale code (e.g. en, es, de, nl, fr, it, pt)
     */
    #[Route('/account/locale/{locale}', name: 'account_locale_switch', requirements: ['locale' => 'en|es|de|nl|fr|it|pt'], methods: ['POST'])]
    public function switch(string $locale, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var list<string> $enabled */
        $enabled = $this->getParameter('kernel.enabled_locales');
        if (!\in_array($locale, $enabled, true)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('account_locale', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user->setPreferredLocale($locale);
        $this->entityManager->flush();
        $this->addFlash('success', 'flash.preferences.locale_saved');

        $request->setLocale($locale);
        $request->getSession()->set('_locale', $locale);

        $target = $request->request->getString('redirect');
        if ('' === $target) {
            $target = $request->headers->get('Referer') ?? $this->generateUrl('dashboard_home');
        }
        if (!str_starts_with($target, '/') || str_starts_with($target, '//')) {
            // Allow absolute URLs only for this host; otherwise fall back.
            $host = $request->getSchemeAndHttpHost();
            if (!str_starts_with($target, $host.'/')) {
                $target = $this->generateUrl('dashboard_home');
            }
        }

        return $this->redirect($this->stripLocaleQuery($target));
    }

    /**
     * Remove `_locale` from a relative or same-host absolute URL.
     */
    private function stripLocaleQuery(string $url): string
    {
        $parts = parse_url($url);
        if (false === $parts) {
            return $this->generateUrl('dashboard_home');
        }

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            unset($query['_locale']);
        }

        $path = ($parts['path'] ?? '/');
        $rebuilt = (isset($parts['scheme']) ? $parts['scheme'].'://'.$parts['host'] : '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .$path
            .([] !== $query ? '?'.http_build_query($query) : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');

        return '' !== $rebuilt ? $rebuilt : $path;
    }
}
