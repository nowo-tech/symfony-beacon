<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * JSON / one-shot endpoints for UI preferences (theme, width, product tour).
 */
#[IsGranted('ROLE_USER')]
final class AccountUiPreferencesAjaxController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Mark or clear the dashboard product tour as seen (JSON).
     */
    #[Route('/account/product-tour/seen', name: 'account_product_tour_seen', methods: ['POST'])]
    public function productTourSeen(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('account_product_tour', $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        try {
            /** @var array{seen?: mixed, page?: mixed} $payload */
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->json(['ok' => false, 'error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $seen = $payload['seen'] ?? null;
        if (!\is_bool($seen)) {
            return $this->json(['ok' => false, 'error' => 'invalid_seen'], Response::HTTP_BAD_REQUEST);
        }

        $page = $payload['page'] ?? null;
        if ($seen) {
            if (\is_string($page) && '' !== $page) {
                $user->markTourPageSeen($page);
            } else {
                $user->markProductTourSeen();
            }
        } else {
            $user->clearProductTourSeen();
        }
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'seen' => $seen,
            'page' => \is_string($page) ? $page : null,
            'pages' => $user->getProductTourSeenPages(),
        ]);
    }

    /**
     * Clear tour flag and open the dashboard tour once.
     */
    #[Route('/account/product-tour/replay', name: 'account_product_tour_replay', methods: ['POST'])]
    public function productTourReplay(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('account_product_tour_replay', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user->clearProductTourSeen();
        $this->entityManager->flush();

        return $this->redirectToRoute('dashboard_home', ['tour' => 1]);
    }

    /**
     * Persist day/night choice from the header theme toggle (JSON).
     */
    #[Route('/account/theme', name: 'account_theme', methods: ['POST'])]
    public function theme(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('account_theme', $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        try {
            /** @var array{theme?: mixed} $payload */
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->json(['ok' => false, 'error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $theme = $payload['theme'] ?? null;
        if (!\is_string($theme) || !\in_array($theme, ['light', 'dark'], true)) {
            return $this->json(['ok' => false, 'error' => 'invalid_theme'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPreferredTheme($theme);
        $this->entityManager->flush();

        return $this->json(['ok' => true, 'theme' => $theme]);
    }

    /**
     * Persist content width from the header toggle (JSON): content | full.
     */
    #[Route('/account/content-width', name: 'account_content_width', methods: ['POST'])]
    public function contentWidth(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('account_content_width', $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        try {
            /** @var array{contentWidth?: mixed} $payload */
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->json(['ok' => false, 'error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $width = $payload['contentWidth'] ?? null;
        if (!\is_string($width) || !\in_array($width, ['content', 'full'], true)) {
            return $this->json(['ok' => false, 'error' => 'invalid_content_width'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPreferredContentWidth($width);
        $this->entityManager->flush();

        return $this->json(['ok' => true, 'contentWidth' => $width]);
    }
}
