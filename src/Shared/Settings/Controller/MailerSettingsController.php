<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Settings\Form\InstanceMailerSettingsType;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin UI for encrypted Mailer DSN and From address.
 */
#[IsGranted('ROLE_ADMIN')]
final class MailerSettingsController extends AbstractController
{
    public function __construct(
        private readonly InstanceSettingsRepository $repository,
        private readonly ConfiguredMailer $configuredMailer,
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

        return $this->render('settings/mailer.html.twig', [
            'form' => $form,
            'settings' => $settings,
            'usingDatabaseDsn' => $this->configuredMailer->isConfiguredFromDatabase(),
            'envFallbackActive' => !$this->configuredMailer->isConfiguredFromDatabase(),
        ]);
    }
}
