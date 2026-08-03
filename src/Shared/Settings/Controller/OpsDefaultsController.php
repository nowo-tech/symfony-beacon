<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use App\Shared\Settings\Form\InstanceOpsDefaultsType;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin UI for instance operational defaults (governance, ingest/security, metrics, inbound, notifications).
 */
#[IsGranted('ROLE_ADMIN')]
final class OpsDefaultsController extends AbstractController
{
    public function __construct(
        private readonly InstanceSettingsRepository $repository,
    ) {
    }

    #[Route('/settings/ops-defaults', name: 'settings_ops_defaults', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $settings = $this->repository->getOrCreate();
        $form = $this->createForm(InstanceOpsDefaultsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (true === $form->get('clearMetricsToken')->getData()) {
                $settings->setMetricsToken(null);
            } else {
                $plainMetrics = trim((string) $form->get('plainMetricsToken')->getData());
                if ('' !== $plainMetrics) {
                    $settings->setMetricsToken($plainMetrics);
                }
            }

            if (true === $form->get('clearInboundWebhookSecret')->getData()) {
                $settings->setInboundWebhookSecret(null);
            } else {
                $plainInbound = trim((string) $form->get('plainInboundWebhookSecret')->getData());
                if ('' !== $plainInbound) {
                    $settings->setInboundWebhookSecret($plainInbound);
                }
            }

            $this->repository->save($settings);
            $this->addFlash('success', 'flash.ops_defaults.saved');

            return $this->redirectToRoute('settings_ops_defaults');
        }

        return $this->render('settings/ops_defaults.html.twig', [
            'form' => $form,
            'settings' => $settings,
        ]);
    }
}
