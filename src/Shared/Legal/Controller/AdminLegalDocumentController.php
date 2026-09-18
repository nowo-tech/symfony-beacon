<?php

declare(strict_types=1);

namespace App\Shared\Legal\Controller;

use App\Shared\Legal\Entity\LegalDocument;
use App\Shared\Legal\Form\LegalDocumentType;
use App\Shared\Legal\LegalPageCatalog;
use App\Shared\Legal\Repository\LegalDocumentRepository;
use App\Shared\Legal\Service\LegalBuiltinHtml;
use App\Shared\Legal\Service\LegalPublishedHtml;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ROLE_ADMIN editor for public legal pages (one row per slug and locale).
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminLegalDocumentController extends AbstractController
{
    public function __construct(
        private readonly LegalDocumentRepository $documents,
        private readonly LegalBuiltinHtml $builtin,
        private readonly LegalPublishedHtml $publishedHtml,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/admin/legal', name: 'admin_legal_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/legal/index.html.twig', [
            'slugs' => LegalPageCatalog::slugs(),
            'locales' => LegalPageCatalog::locales(),
            'custom' => $this->documents->customSlugLocaleMap(),
        ]);
    }

    #[Route(
        '/admin/legal/{slug}/{locale}',
        name: 'admin_legal_edit',
        requirements: [
            'slug' => LegalPageCatalog::SLUG_REQUIREMENT,
            'locale' => LegalPageCatalog::LOCALE_REQUIREMENT,
        ],
        methods: ['GET', 'POST'],
    )]
    public function edit(Request $request, string $slug, string $locale): Response
    {
        $document = $this->documents->findOneBySlugAndLocale($slug, $locale);
        $isNew = !$document instanceof LegalDocument;
        if ($isNew) {
            $document = new LegalDocument($slug, $locale);
            $document->setTitle($this->builtin->title($slug, $locale));
            $document->setBody($this->builtin->body($slug, $locale));
        }

        $form = $this->createForm(LegalDocumentType::class, $document);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $document->setBody($this->publishedHtml->sanitize($document->getBody()));
            if ('' === trim(strip_tags($document->getBody()))) {
                if ($this->entityManager->contains($document)) {
                    $this->entityManager->refresh($document);
                }
                $this->addFlash('error', 'legal.admin.empty_body');

                return $this->redirectToRoute('admin_legal_edit', ['slug' => $slug, 'locale' => $locale]);
            }

            $this->documents->save($document);
            $this->addFlash('success', 'legal.admin.saved');

            return $this->redirectToRoute('admin_legal_edit', ['slug' => $slug, 'locale' => $locale]);
        }

        return $this->render('admin/legal/edit.html.twig', [
            'form' => $form,
            'reset_form' => $this->resetForm($slug, $locale),
            'slug' => $slug,
            'locale' => $locale,
        ]);
    }

    #[Route(
        '/admin/legal/{slug}/{locale}/reset',
        name: 'admin_legal_reset',
        requirements: [
            'slug' => LegalPageCatalog::SLUG_REQUIREMENT,
            'locale' => LegalPageCatalog::LOCALE_REQUIREMENT,
        ],
        methods: ['POST'],
    )]
    public function reset(Request $request, string $slug, string $locale): RedirectResponse
    {
        $form = $this->resetForm($slug, $locale);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'legal.admin.invalid_csrf');

            return $this->redirectToRoute('admin_legal_edit', ['slug' => $slug, 'locale' => $locale]);
        }

        $document = $this->documents->findOneBySlugAndLocale($slug, $locale);
        if ($document instanceof LegalDocument) {
            $this->documents->remove($document);
        }
        $this->addFlash('success', 'legal.admin.reset_done');

        return $this->redirectToRoute('admin_legal_edit', ['slug' => $slug, 'locale' => $locale]);
    }

    /**
     * @return FormInterface<null>
     */
    private function resetForm(string $slug, string $locale): FormInterface
    {
        return $this->createFormBuilder(null, [
            'csrf_token_id' => 'legal_document_reset',
        ])
            ->setAction($this->generateUrl('admin_legal_reset', ['slug' => $slug, 'locale' => $locale]))
            ->setMethod('POST')
            ->getForm();
    }
}
