<?php

declare(strict_types=1);

namespace App\Shared\Legal\Controller;

use App\Shared\Legal\Entity\LegalDocument;
use App\Shared\Legal\LegalPageCatalog;
use App\Shared\Legal\Repository\LegalDocumentRepository;
use App\Shared\Legal\Service\LegalPublishedHtml;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public legal pages. A stored operator document wins; otherwise the built-in seed is shown.
 */
final class LegalController extends AbstractController
{
    public function __construct(
        private readonly LegalDocumentRepository $documents,
        private readonly LegalPublishedHtml $publishedHtml,
    ) {
    }

    #[Route(
        '/{_locale}/legal/notice',
        name: 'legal_notice',
        requirements: ['_locale' => LegalPageCatalog::LOCALE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function notice(Request $request): Response
    {
        return $this->page($request, 'notice', 'legal/notice.html.twig');
    }

    #[Route(
        '/{_locale}/legal/privacy',
        name: 'legal_privacy',
        requirements: ['_locale' => LegalPageCatalog::LOCALE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function privacy(Request $request): Response
    {
        return $this->page($request, 'privacy', 'legal/privacy.html.twig');
    }

    #[Route(
        '/{_locale}/legal/terms',
        name: 'legal_terms',
        requirements: ['_locale' => LegalPageCatalog::LOCALE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function terms(Request $request): Response
    {
        return $this->page($request, 'terms', 'legal/terms.html.twig');
    }

    #[Route(
        '/{_locale}/legal/cookies',
        name: 'legal_cookies',
        requirements: ['_locale' => LegalPageCatalog::LOCALE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function cookies(Request $request): Response
    {
        return $this->page($request, 'cookies', 'legal/cookies.html.twig');
    }

    private function page(Request $request, string $slug, string $fallbackTemplate): Response
    {
        $document = $this->documents->findOneBySlugAndLocale($slug, $request->getLocale());
        if ($document instanceof LegalDocument && '' !== trim(strip_tags($document->getBody()))) {
            return $this->render('legal/stored.html.twig', [
                'title' => $document->getTitle(),
                'body' => $this->publishedHtml->sanitize($document->getBody()),
                'current' => $slug,
            ]);
        }

        return $this->render($fallbackTemplate);
    }
}
