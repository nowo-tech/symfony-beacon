<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Identity\Entity\User;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Settings\Form\InstanceMailerSettingsType;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Admin UI for encrypted Mailer DSN and From address (magic-login + notification email).
 */
#[IsGranted('ROLE_ADMIN')]
final class MailerSettingsController extends AbstractController
{
    public function __construct(
        private readonly InstanceSettingsRepository $repository,
        private readonly ConfiguredMailer $configuredMailer,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/settings/mailer', name: 'settings_mailer', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $settings = $this->repository->getOrCreate();
        $form = $this->createForm(InstanceMailerSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (true === $form->get('clearMailerDsn')->getData()) {
                $settings->setMailerDsn(null);
            } else {
                $plainDsn = trim((string) $form->get('plainMailerDsn')->getData());
                if ('' !== $plainDsn) {
                    $settings->setMailerDsn($plainDsn);
                }
            }

            $this->repository->save($settings);
            $this->configuredMailer->reset();
            $this->addFlash('success', 'flash.mailer.saved');

            return $this->redirectToRoute('settings_mailer');
        }

        $user = $this->getUser();
        $defaultSampleTo = $user instanceof User ? $user->getEmail() : '';

        return $this->render('settings/mailer.html.twig', [
            'form' => $form,
            'settings' => $settings,
            'usingDatabaseDsn' => $this->configuredMailer->isConfiguredFromDatabase(),
            'envFallbackActive' => !$this->configuredMailer->isConfiguredFromDatabase(),
            'magicLoginAvailable' => $this->configuredMailer->isMagicLoginAvailable(),
            'defaultSampleTo' => $defaultSampleTo,
        ]);
    }

    #[Route('/settings/mailer/test', name: 'settings_mailer_test', methods: ['POST'])]
    public function sendSample(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('mailer_sample', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->configuredMailer->isMagicLoginAvailable()) {
            $this->addFlash('error', 'flash.mailer.sample_unavailable');

            return $this->redirectToRoute('settings_mailer');
        }

        $to = trim($request->request->getString('to'));
        if ('' === $to) {
            $user = $this->getUser();
            $to = $user instanceof User ? $user->getEmail() : '';
        }

        if ('' === $to || false === filter_var($to, \FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'flash.mailer.sample_invalid_recipient');

            return $this->redirectToRoute('settings_mailer');
        }

        try {
            $this->configuredMailer->sendSample($to, $this->translator);
            $this->addFlash('success', 'flash.mailer.sample_sent');
        } catch (Throwable $e) {
            $this->logger->error('Mailer sample send failed.', [
                'exception' => $e,
                'to' => $to,
            ]);
            $this->addFlash('error', 'flash.mailer.sample_failed');
        }

        return $this->redirectToRoute('settings_mailer');
    }
}
