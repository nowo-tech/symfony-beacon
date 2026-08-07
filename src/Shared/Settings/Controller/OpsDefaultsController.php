<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use App\Shared\Form\EncryptedSecretFormApplier;
use App\Shared\Settings\Form\InstanceOpsDefaultsType;
use App\Shared\Settings\OpsDefaultsSection;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin UI for instance operational defaults (one route per section tab).
 */
#[IsGranted('ROLE_ADMIN')]
final class OpsDefaultsController extends AbstractController
{
    public function __construct(
        private readonly InstanceSettingsRepository $repository,
    ) {
    }

    #[Route('/settings/ops-defaults', name: 'settings_ops_defaults', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('settings_ops_defaults_section', [
            'section' => OpsDefaultsSection::Governance->value,
        ]);
    }

    #[Route(
        '/settings/ops-defaults/{section}',
        name: 'settings_ops_defaults_section',
        requirements: ['section' => 'governance|ingest|metrics|inbound|notifications'],
        methods: ['GET', 'POST'],
    )]
    public function edit(Request $request, string $section): Response
    {
        $sectionEnum = OpsDefaultsSection::tryFrom($section);
        if (null === $sectionEnum) {
            throw $this->createNotFoundException();
        }

        $settings = $this->repository->getOrCreate();
        $form = $this->createForm(InstanceOpsDefaultsType::class, $settings, [
            'section' => $sectionEnum,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (OpsDefaultsSection::Metrics === $sectionEnum) {
                EncryptedSecretFormApplier::apply(
                    true === $form->get('clearMetricsToken')->getData(),
                    trim((string) $form->get('plainMetricsToken')->getData()),
                    $settings->setMetricsToken(...),
                );
            }

            if (OpsDefaultsSection::Inbound === $sectionEnum) {
                EncryptedSecretFormApplier::apply(
                    true === $form->get('clearInboundWebhookSecret')->getData(),
                    trim((string) $form->get('plainInboundWebhookSecret')->getData()),
                    $settings->setInboundWebhookSecret(...),
                );
            }

            $this->repository->save($settings);
            $this->addFlash('success', 'flash.ops_defaults.saved');

            return $this->redirectToRoute('settings_ops_defaults_section', [
                'section' => $sectionEnum->value,
            ]);
        }

        return $this->render('settings/ops_defaults.html.twig', [
            'form' => $form,
            'settings' => $settings,
            'section' => $sectionEnum,
            'sections' => OpsDefaultsSection::cases(),
        ]);
    }
}
