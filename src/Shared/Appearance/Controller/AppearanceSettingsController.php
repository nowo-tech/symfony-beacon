<?php

declare(strict_types=1);

namespace App\Shared\Appearance\Controller;

use App\Shared\Appearance\Form\SiteAppearanceType;
use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use App\Shared\Appearance\SiteAppearanceProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AppearanceSettingsController extends AbstractController
{
    public function __construct(
        private readonly SiteAppearanceRepository $repository,
        private readonly SiteAppearanceProvider $appearanceProvider,
    ) {
    }

    #[Route('/settings/appearance', name: 'settings_appearance', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $appearance = $this->repository->getOrCreate();
        $form = $this->createForm(SiteAppearanceType::class, $appearance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->request->has('reset')) {
                $appearance->resetToDefaults();
            }

            $this->repository->save($appearance);
            $this->appearanceProvider->refresh();
            $this->addFlash('success', 'flash.appearance.saved');

            return $this->redirectToRoute('settings_appearance');
        }

        return $this->render('settings/appearance.html.twig', [
            'form' => $form,
        ]);
    }
}
