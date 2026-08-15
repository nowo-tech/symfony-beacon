<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\AccountSecurityActivity;
use App\Identity\Entity\PasswordHistory;
use App\Identity\Entity\User;
use App\Identity\Form\AccountDisplayType;
use App\Identity\Form\AccountProductTourReplayType;
use App\Identity\Form\AccountProfileSensitiveType;
use App\Identity\Form\AccountProfileType;
use App\Identity\Form\AccountSecurityType;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\AccountSocialAccounts;
use App\Identity\UserDisplayPreferenceDefaults;
use App\Issues\IssuePanelIds;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Notifications\Service\MemberAlertPreferenceManager;
use App\Notifications\Service\WebPushClientFactory;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Service\AccessibleProjectsProvider;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
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
        private readonly UserActionRepository $userActionRepository,
        private readonly ProjectMembershipRepository $projectMembershipRepository,
        private readonly UserGroupMembershipRepository $userGroupMembershipRepository,
        private readonly AccessibleProjectsProvider $accessibleProjects,
        private readonly MemberAlertPreferenceManager $memberAlertPreferenceManager,
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly WebPushClientFactory $webPushFactory,
        private readonly AccountSocialAccounts $accountSocialAccounts,
        private readonly PasswordExpiryServiceInterface $passwordExpiryService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    #[Route('/account', name: 'account_index', methods: ['GET'])]
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
        $previousPhone = $user->getPhone();
        $previousPhoneVerifiedAt = $user->getPhoneVerifiedAt();
        $previousSlackUserId = $user->getSlackUserId();

        // Distinct form names so only the submitted panel is handled (shared block prefix for FormKit keys).
        $form = $this->formFactory->createNamed('user_profile', AccountProfileType::class, $user);
        $sensitiveForm = $this->formFactory->createNamed('user_profile_sensitive', AccountProfileSensitiveType::class, $user);
        $form->handleRequest($request);
        $sensitiveForm->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $phone = $user->getPhone();
            if (null === $phone || '' === $phone || $phone !== $previousPhone) {
                $user->setPhoneVerifiedAt(null);
            } elseif ($previousPhoneVerifiedAt instanceof DateTimeImmutable) {
                $user->setPhoneVerifiedAt($previousPhoneVerifiedAt);
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'flash.preferences.profile_saved');

            return $this->redirectToRoute('account_profile');
        }

        if ($sensitiveForm->isSubmitted()) {
            if (!$sensitiveForm->isValid()) {
                // Mapped fields may already have mutated the security user; revert until a valid save.
                $user->setEmail($previousEmail);
                $user->setSlackUserId($previousSlackUserId);

                return $this->renderProfile($form, $sensitiveForm, $user);
            }

            $currentPassword = (string) $sensitiveForm->get('currentPassword')->getData();
            if ('' === $currentPassword || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                // Revert before re-render: a mutated security user email can invalidate the session.
                $user->setEmail($previousEmail);
                $user->setSlackUserId($previousSlackUserId);
                $sensitiveForm->get('currentPassword')->addError(new FormError($this->translator->trans('preferences.error.current_password')));

                return $this->renderProfile($form, $sensitiveForm, $user);
            }

            if ($user->getEmail() !== $previousEmail) {
                $conflict = $this->userRepository->findOneByEmail($user->getEmail());
                if ($conflict instanceof User && $conflict->getId() !== $user->getId()) {
                    $user->setEmail($previousEmail);
                    $sensitiveForm->get('email')->addError(new FormError($this->translator->trans('preferences.error.email_in_use')));

                    return $this->renderProfile($form, $sensitiveForm, $user);
                }
            }

            $newSlack = $user->getSlackUserId();
            $slackChanged = ($newSlack ?? '') !== ($previousSlackUserId ?? '');
            if ($slackChanged && null !== $newSlack && '' !== $newSlack) {
                $slackConflict = $this->userRepository->findOneBySlackUserId($newSlack);
                if ($slackConflict instanceof User && $slackConflict->getId() !== $user->getId()) {
                    $user->setSlackUserId($previousSlackUserId);
                    $sensitiveForm->get('slackUserId')->addError(new FormError($this->translator->trans('preferences.error.slack_user_id_in_use')));

                    return $this->renderProfile($form, $sensitiveForm, $user);
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'flash.preferences.profile_saved');

            return $this->redirectToRoute('account_profile');
        }

        return $this->renderProfile($form, $sensitiveForm, $user);
    }

    #[Route('/account/projects', name: 'account_projects', methods: ['GET'])]
    public function projects(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('account/projects.html.twig', [
            'project_memberships' => $this->projectMembershipRepository->findByUser($user),
        ]);
    }

    #[Route('/account/groups', name: 'account_groups', methods: ['GET'])]
    public function groups(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('account/groups.html.twig', [
            'group_memberships' => $this->userGroupMembershipRepository->findByUser($user),
        ]);
    }

    /**
     * @param FormInterface<mixed> $form
     * @param FormInterface<mixed> $sensitiveForm
     */
    private function renderProfile(FormInterface $form, FormInterface $sensitiveForm, User $user): Response
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
            'sensitive_form' => $sensitiveForm,
            'profile_user' => $user,
            'profile_roles' => $roleLabels,
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

                return $this->renderSecurity($form);
            }

            if ('' === $currentPassword || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(new FormError($this->translator->trans('preferences.error.current_password')));

                return $this->renderSecurity($form);
            }

            if ($this->passwordHasher->isPasswordValid($user, $plainPassword)) {
                $form->get('plainPassword')->addError(new FormError($this->translator->trans('preferences.error.password_same_as_current')));

                return $this->renderSecurity($form);
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $user->eraseCredentials();
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.preferences.password_saved');

            return $this->redirectToRoute('account_security');
        }

        return $this->renderSecurity($form);
    }

    #[Route('/account/security/history', name: 'account_security_history', methods: ['GET'])]
    public function securityHistory(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('account/security_history.html.twig', [
            'password_changed_at' => $user->getPasswordChangedAt(),
            'password_change_history' => $this->passwordChangeHistoryFor($user),
        ]);
    }

    #[Route('/account/security/activity', name: 'account_security_activity', methods: ['GET'])]
    public function securityActivity(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('account/security_activity.html.twig', [
            'security_actions' => $this->userActionRepository->findForUser(
                $user,
                AccountSecurityActivity::actionTypes(),
                limit: AccountSecurityActivity::TIMELINE_LIMIT,
            ),
        ]);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function renderSecurity(FormInterface $form): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('account/security.html.twig', [
            'form' => $form,
            'social_login_enabled' => $this->accountSocialAccounts->isSocialLoginEnabled(),
            'linked_social_accounts' => $this->accountSocialAccounts->linkedFor($user),
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
        return $this->handleDisplaySection(
            $request,
            AccountDisplayType::SECTION_APPEARANCE,
            'account_display',
            'account/display.html.twig',
        );
    }

    #[Route('/account/display/panels', name: 'account_display_panels', methods: ['GET', 'POST'])]
    public function displayPanels(Request $request): Response
    {
        return $this->handleDisplaySection(
            $request,
            AccountDisplayType::SECTION_PANELS,
            'account_display_panels',
            'account/display_panels.html.twig',
        );
    }

    #[Route('/account/display/tours', name: 'account_display_tours', methods: ['GET', 'POST'])]
    public function displayTours(Request $request): Response
    {
        return $this->handleDisplaySection(
            $request,
            AccountDisplayType::SECTION_TOURS,
            'account_display_tours',
            'account/display_tours.html.twig',
        );
    }

    #[Route('/account/display/notifications', name: 'account_display_notifications', methods: ['GET'])]
    public function displayNotifications(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->healDisplayPreferencesIfNeeded($user);

        $pushAvailable = $this->webPushFactory->isConfigured();
        $accessibleProjects = $this->accessibleProjects->forUser($user);
        $projectRows = $this->memberAlertPreferenceManager->projectRowsForUi($user, $accessibleProjects);

        /** @var list<array{uuid: string, name: string, hasOverrides: bool, formData: array<string, mixed>}> $projects */
        $projects = [];
        foreach ($projectRows as $row) {
            $project = $row['project'];
            $projects[] = [
                'uuid' => $project->getUuid(),
                'name' => $project->getName(),
                'hasOverrides' => $row['hasOverrides'],
                'formData' => [
                    'enabled' => $row['enabled'],
                    'resetOverrides' => false,
                    'events' => MemberAlertEvent::mapEventsToFormKeys($row['events']),
                ],
            ];
        }

        return $this->render('account/display_notifications.html.twig', [
            'push_available' => $pushAvailable,
            'member_alert_initial' => [
                'memberAlertsEnabled' => $user->isMemberAlertsEnabled(),
                'events' => MemberAlertEvent::mapEventsToFormKeys(
                    $this->memberAlertPreferenceManager->accountEventsForUi($user),
                ),
                'pushNotificationsEnabled' => $user->isPushNotificationsEnabled(),
            ],
            'member_alert_projects' => $projects,
        ]);
    }

    private function handleDisplaySection(
        Request $request,
        string $section,
        string $routeName,
        string $template,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->healDisplayPreferencesIfNeeded($user);

        /** @var list<string> $enabledLocales */
        $enabledLocales = $this->getParameter('kernel.enabled_locales');
        $pushAvailable = $this->webPushFactory->isConfigured();

        $form = $this->createForm(AccountDisplayType::class, $user, [
            'enabled_locales' => $enabledLocales,
            'push_available' => $pushAvailable,
            'section' => $section,
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() && $form->has('productTourEnabledPages')) {
            $form->get('productTourEnabledPages')->setData($user->getEnabledProductTourPages());
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->has('productTourEnabledPages')) {
                /** @var list<string>|array<int, mixed> $enabledTours */
                $enabledTours = $form->get('productTourEnabledPages')->getData() ?? [];
                $user->syncEnabledProductTourPages(\is_array($enabledTours) ? $enabledTours : []);
            }

            if ($form->has('pushNotificationsEnabled') && !$user->isPushNotificationsEnabled()) {
                foreach ($this->pushSubscriptionRepository->findByUser($user) as $subscription) {
                    $this->entityManager->remove($subscription);
                }
            }

            $this->entityManager->flush();

            if (AccountDisplayType::SECTION_APPEARANCE === $section) {
                $locale = $user->getPreferredLocale();
                if (\is_string($locale) && '' !== $locale) {
                    $request->setLocale($locale);
                    $request->getSession()->set('_locale', $locale);
                }
            }

            $this->addFlash('success', 'flash.preferences.display_saved');

            return $this->redirectToRoute($routeName);
        }

        $vars = [
            'form' => $form,
            'issue_panel_ids' => IssuePanelIds::all(),
            'push_available' => $pushAvailable,
        ];
        if (AccountDisplayType::SECTION_TOURS === $section) {
            $vars['replayForm'] = $this->createForm(AccountProductTourReplayType::class);
        }

        return $this->render($template, $vars);
    }

    /**
     * Persist canonical defaults for legacy null preference columns (skips anonymized users).
     */
    private function healDisplayPreferencesIfNeeded(User $user): void
    {
        if ($user->isAnonymized()) {
            return;
        }

        if (!\in_array(null, [$user->getPreferredLocaleRaw(), $user->getPreferredThemeRaw(), $user->getPreferredMotionRaw(), $user->getPreferredContrastRaw(), $user->getPreferredContentWidthRaw(), $user->getPreferredUiDensityRaw(), $user->getPreferredFontScaleRaw(), $user->getPreferredSidebarRaw()], true)
        ) {
            return;
        }

        UserDisplayPreferenceDefaults::applyMissing(
            $user,
            (string) $this->getParameter('default_locale'),
        );
        $this->entityManager->flush();
    }
}
