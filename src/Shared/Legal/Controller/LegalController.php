<?php

declare(strict_types=1);

namespace App\Shared\Legal\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public legal / privacy pages (operator-editable placeholders).
 *
 * Canonical URLs include /{_locale}/legal/…; bare /legal/… redirects to DEFAULT_LOCALE.
 */
final class LegalController extends AbstractController
{
    private const string LOCALE_REQUIREMENT = 'en|es|de|nl|fr|it|pt';

    #[Route(
        '/{_locale}/legal/notice',
        name: 'legal_notice',
        requirements: ['_locale' => self::LOCALE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function notice(): Response
    {
        return $this->render('legal/notice.html.twig');
    }

    #[Route(
        '/{_locale}/legal/privacy',
        name: 'legal_privacy',
        requirements: ['_locale' => self::LOCALE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }

    #[Route(
        '/{_locale}/legal/terms',
        name: 'legal_terms',
        requirements: ['_locale' => self::LOCALE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function terms(): Response
    {
        return $this->render('legal/terms.html.twig');
    }

    #[Route(
        '/{_locale}/legal/cookies',
        name: 'legal_cookies',
        requirements: ['_locale' => self::LOCALE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function cookies(): Response
    {
        return $this->render('legal/cookies.html.twig');
    }
}
