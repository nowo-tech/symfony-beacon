<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/account/locale/{locale}', name: 'account_locale_switch', methods: ['POST'], requirements: ['locale' => 'en|es'])]
    public function switch(string $locale, Request $request): Response
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

        $request->setLocale($locale);
        $request->getSession()->set('_locale', $locale);

        $target = $request->request->getString('redirect') ?: $request->headers->get('Referer') ?: $this->generateUrl('dashboard_home');
        if (!\is_string($target) || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
            // Allow absolute URLs only for this host; otherwise fall back.
            $host = $request->getSchemeAndHttpHost();
            if (!\is_string($target) || !str_starts_with($target, $host.'/')) {
                $target = $this->generateUrl('dashboard_home');
            }
        }

        return $this->redirect($this->stripLocaleQuery((string) $target));
    }

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
