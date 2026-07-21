<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\PasswordHistory;
use App\Identity\Entity\User;
use App\Identity\Form\AccountDisplayType;
use App\Identity\Form\AccountProfileType;
use App\Identity\Form\AccountSecurityType;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserRepository;
use App\Issues\IssuePanelIds;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Notifications\Service\WebPushClientFactory;
use App\Project\Repository\ProjectMembershipRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Account profile, security, and display preference screens.
 */
#[IsGranted('ROLE_USER')]
final class AccountPreferencesController extends AbstractController
{
    /** Mirrors config/packages/nowo_password_policy.yaml expiry_days for profile summary. */
    private const int PASSWORD_EXPIRY_DAYS = 90;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ProjectMembershipRepository $projectMembershipRepository,
        private readonly UserGroupMembershipRepository $userGroupMembershipRepository,
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly WebPushClientFactory $webPushFactory,
        private readonly PasswordExpiryServiceInterface $passwordExpiryService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/account/preferences', name: 'account_preferences', methods: ['GET'])]
    public function preferencesIndex(): RedirectResponse
    {
        return $this->redirectToRoute('account_profile');
    }

    #[Route('/account/profile', name: 'account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $previousEmail = $user->getEmail();

        $form = $this->createForm(AccountProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($user->getEmail() !== $previousEmail) {
                $conflict = $this->userRepository->findOneByEmail($user->getEmail());
                if ($conflict instanceof User && $conflict->getId() !== $user->getId()) {
                    $form->get('email')->addError(new FormError($this->translator->trans('preferences.error.email_in_use')));

                    return $this->renderProfile($form, $user);
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'flash.preferences.profile_saved');

            return $this->redirectToRoute('account_profile');
        }

        return $this->renderProfile($form, $user);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function renderProfile(FormInterface $form, User $user): Response
    {
        $passwordChangedAt = $user->getPasswordChangedAt();
        $passwordExpiresAt = null;
        $passwordDaysRemaining = null;
        if ($passwordChangedAt instanceof DateTimeInterface) {
            $passwordExpiresAt = DateTimeImmutable::createFromInterface($passwordChangedAt)
                ->modify('+'.self::PASSWORD_EXPIRY_DAYS.' days');
            $now = new DateTimeImmutable();
            $passwordDaysRemaining = (int) $now->diff($passwordExpiresAt)->format('%r%a');
        }

        $roleLabels = [];
        foreach ($user->getRoles() as $role) {
            if ('ROLE_USER' === $role) {
                continue;
            }
            $roleLabels[] = match ($role) {
                'ROLE_ADMIN' => 'preferences.profile.role_admin',
                default => $role,
            };
        }

        return $this->render('account/profile.html.twig', [
            'form' => $form,
            'profile_user' => $user,
            'profile_roles' => $roleLabels,
            'project_memberships' => $this->projectMembershipRepository->findByUser($user),
            'group_memberships' => $this->userGroupMembershipRepository->findByUser($user),
            'password_changed_at' => $passwordChangedAt,
            'password_expires_at' => $passwordExpiresAt,
            'password_days_remaining' => $passwordDaysRemaining,
            'password_expired' => $this->passwordExpiryService->isPasswordExpired(),
            'password_expiry_days' => self::PASSWORD_EXPIRY_DAYS,
        ]);
    }

    #[Route('/account/security', name: 'account_security', methods: ['GET', 'POST'])]
    public function security(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(AccountSecurityType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $user->getPlainPassword();
            $currentPassword = (string) $form->get('currentPassword')->getData();

            if (!\is_string($plainPassword) || '' === $plainPassword) {
                $form->get('plainPassword')->addError(new FormError($this->translator->trans('preferences.error.password_required')));

                return $this->renderSecurity($form, $user);
            }

            if ('' === $currentPassword || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(new FormError($this->translator->trans('preferences.error.current_password')));

                return $this->renderSecurity($form, $user);
            }

            if ($this->passwordHasher->isPasswordValid($user, $plainPassword)) {
                $form->get('plainPassword')->addError(new FormError($this->translator->trans('preferences.error.password_same_as_current')));

                return $this->renderSecurity($form, $user);
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $user->eraseCredentials();
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.preferences.password_saved');

            return $this->redirectToRoute('account_security');
        }

        return $this->renderSecurity($form, $user);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function renderSecurity(FormInterface $form, User $user): Response
    {
        return $this->render('account/security.html.twig', [
            'form' => $form,
            'password_changed_at' => $user->getPasswordChangedAt(),
            'password_change_history' => $this->passwordChangeHistoryFor($user),
        ]);
    }

    /**
     * Timestamps of retained password changes (hashes never exposed).
     *
     * @return list<DateTimeInterface>
     */
    private function passwordChangeHistoryFor(User $user): array
    {
        $dates = [];
        foreach ($user->getPasswordHistory() as $entry) {
            if (!$entry instanceof PasswordHistory) {
                continue;
            }
            $createdAt = $entry->getCreatedAt();
            if ($createdAt instanceof DateTimeInterface) {
                $dates[] = $createdAt;
            }
        }

        usort(
            $dates,
            static fn (DateTimeInterface $a, DateTimeInterface $b): int => $b <=> $a,
        );

        return $dates;
    }

    #[Route('/account/display', name: 'account_display', methods: ['GET', 'POST'])]
    public function display(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var list<string> $enabledLocales */
        $enabledLocales = $this->getParameter('kernel.enabled_locales');

        $form = $this->createForm(AccountDisplayType::class, $user, [
            'enabled_locales' => $enabledLocales,
            'push_available' => $this->webPushFactory->isConfigured(),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            $form->get('productTourEnabledPages')->setData($user->getEnabledProductTourPages());
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var list<string>|array<int, mixed> $enabledTours */
            $enabledTours = $form->get('productTourEnabledPages')->getData() ?? [];
            $user->syncEnabledProductTourPages(\is_array($enabledTours) ? $enabledTours : []);

            if ($form->has('pushNotificationsEnabled') && !$user->isPushNotificationsEnabled()) {
                foreach ($this->pushSubscriptionRepository->findByUser($user) as $subscription) {
                    $this->entityManager->remove($subscription);
                }
            }

            $this->entityManager->flush();

            $locale = $user->getPreferredLocale();
            if (\is_string($locale) && '' !== $locale) {
                $request->setLocale($locale);
                $request->getSession()->set('_locale', $locale);
            }

            $this->addFlash('success', 'flash.preferences.display_saved');

            return $this->redirectToRoute('account_display');
        }

        return $this->render('account/display.html.twig', [
            'form' => $form,
            'issue_panel_ids' => IssuePanelIds::all(),
            'push_available' => $this->webPushFactory->isConfigured(),
        ]);
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
}
