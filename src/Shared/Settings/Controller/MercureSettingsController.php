<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Settings\Form\InstanceMercureSettingsType;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin UI for optional Mercure live member alerts.
 */
#[IsGranted('ROLE_ADMIN')]
final class MercureSettingsController extends AbstractController
{
    public function __construct(
        private readonly InstanceSettingsRepository $repository,
        private readonly ConfiguredMercure $configuredMercure,
    ) {
    }

    #[Route('/settings/mercure', name: 'settings_mercure', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $settings = $this->repository->getOrCreate();
        $form = $this->createForm(InstanceMercureSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (true === $form->get('clearMercureJwtSecret')->getData()) {
                $settings->setMercureJwtSecret(null);
            } else {
                $plainSecret = trim((string) $form->get('plainMercureJwtSecret')->getData());
                if ('' !== $plainSecret) {
                    $settings->setMercureJwtSecret($plainSecret);
                }
            }

            $this->repository->save($settings);
            $this->configuredMercure->reset();
            $this->addFlash('success', 'flash.mercure.saved');

            return $this->redirectToRoute('settings_mercure');
        }

        return $this->render('settings/mercure.html.twig', [
            'form' => $form,
            'settings' => $settings,
            'mercure_active' => $this->configuredMercure->isEnabled(),
            'using_database_url' => $this->configuredMercure->isUsingDatabaseUrl(),
            'using_database_secret' => $this->configuredMercure->isUsingDatabaseSecret(),
            'env_url_configured' => $this->configuredMercure->envUrlConfigured(),
            'env_jwt_configured' => $this->configuredMercure->envJwtConfigured(),
            'resolved_public_url' => $this->configuredMercure->getPublicUrl(),
        ]);
    }
}
