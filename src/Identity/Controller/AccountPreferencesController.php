<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Form\AccountDisplayType;
use App\Identity\Form\AccountProfileType;
use App\Identity\Form\AccountSecurityType;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/account/preferences', name: 'account_preferences', methods: ['GET'])]
    public function preferencesIndex(): Response
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

                    return $this->render('account/profile.html.twig', [
                        'form' => $form,
                    ]);
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'flash.preferences.profile_saved');

            return $this->redirectToRoute('account_profile');
        }

        return $this->render('account/profile.html.twig', [
            'form' => $form,
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
            $plainPassword = $form->get('plainPassword')->getData();
            $currentPassword = (string) $form->get('currentPassword')->getData();

            if (!\is_string($plainPassword) || '' === $plainPassword) {
                $form->get('plainPassword')->addError(new FormError($this->translator->trans('preferences.error.password_required')));

                return $this->render('account/security.html.twig', [
                    'form' => $form,
                ]);
            }

            if ('' === $currentPassword || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(new FormError($this->translator->trans('preferences.error.current_password')));

                return $this->render('account/security.html.twig', [
                    'form' => $form,
                ]);
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.preferences.password_saved');

            return $this->redirectToRoute('account_security');
        }

        return $this->render('account/security.html.twig', [
            'form' => $form,
        ]);
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
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
        ]);
    }
}
