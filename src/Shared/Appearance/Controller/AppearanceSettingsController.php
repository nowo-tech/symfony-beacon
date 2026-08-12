<?php

declare(strict_types=1);

namespace App\Shared\Appearance\Controller;

use App\Shared\Appearance\AppearanceSettingsSection;
use App\Shared\Appearance\AppearanceSettingsSubtab;
use App\Shared\Appearance\AppearanceThemePresets;
use App\Shared\Appearance\Form\AppearanceThemePickerType;
use App\Shared\Appearance\Form\SiteAppearanceType;
use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use App\Shared\Appearance\SiteAppearanceProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin UI for site brand, themes, layout chrome, and palette colors.
 */
#[IsGranted('ROLE_ADMIN')]
final class AppearanceSettingsController extends AbstractController
{
    public function __construct(
        private readonly SiteAppearanceRepository $repository,
        private readonly SiteAppearanceProvider $appearanceProvider,
    ) {
    }

    #[Route('/admin/appearance', name: 'admin_appearance', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_appearance_section', [
            'section' => AppearanceSettingsSection::Themes->value,
        ]);
    }

    #[Route(
        '/admin/appearance/{section}',
        name: 'admin_appearance_section',
        requirements: ['section' => 'themes|brand|layout|colors'],
        defaults: ['sub' => null],
        methods: ['GET', 'POST'],
        priority: 10,
    )]
    #[Route(
        '/admin/appearance/{section}/{sub}',
        name: 'admin_appearance_section',
        requirements: [
            'section' => 'themes|brand|layout|colors',
            'sub' => 'accents|status|surfaces',
        ],
        methods: ['GET', 'POST'],
    )]
    public function edit(Request $request, string $section, ?string $sub = null): Response
    {
        $sectionEnum = AppearanceSettingsSection::tryFrom($section);
        if (null === $sectionEnum) {
            throw $this->createNotFoundException();
        }

        $subEnum = $this->resolveSubtab($sectionEnum, $sub);
        if (null !== $sectionEnum->defaultSubtab() && null === $subEnum) {
            return $this->redirectToRoute('admin_appearance_section', [
                'section' => $sectionEnum->value,
                'sub' => $sectionEnum->defaultSubtab()->value,
            ]);
        }

        $appearance = $this->repository->getOrCreate();
        $redirectParams = $this->sectionParams($sectionEnum, $subEnum);
        $themeForm = $this->createForm(AppearanceThemePickerType::class, null, [
            'action' => $this->generateUrl('admin_appearance_section', $redirectParams),
            'method' => 'POST',
            'csrf_token_id' => 'appearance_theme',
        ]);
        $themeForm->handleRequest($request);

        if (
            AppearanceSettingsSection::Themes === $sectionEnum
            && $themeForm->isSubmitted()
            && $themeForm->isValid()
        ) {
            /** @var array{apply_theme?: string|null} $data */
            $data = $themeForm->getData();
            $themeId = (string) ($data['apply_theme'] ?? '');
            if (AppearanceThemePresets::apply($appearance, $themeId)) {
                $this->repository->save($appearance);
                $this->appearanceProvider->refresh();
                $this->addFlash('success', 'flash.appearance.theme_applied');

                return $this->redirectToRoute('admin_appearance_section', $redirectParams);
            }

            $this->addFlash('error', 'flash.appearance.theme_unknown');

            return $this->redirectToRoute('admin_appearance_section', $redirectParams);
        }

        $form = null;
        if (AppearanceSettingsSection::Themes !== $sectionEnum) {
            $form = $this->createForm(SiteAppearanceType::class, $appearance, [
                'section' => $sectionEnum,
                'subtab' => $subEnum,
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                if ($request->request->has('reset')) {
                    $appearance->resetToDefaults();
                } elseif (AppearanceSettingsSection::Colors === $sectionEnum) {
                    $appearance->setThemeId(AppearanceThemePresets::matchLightId($appearance));
                    $appearance->setThemeIdDark(AppearanceThemePresets::matchDarkId($appearance));
                }

                $this->repository->save($appearance);
                $this->appearanceProvider->refresh();
                $this->addFlash('success', 'flash.appearance.saved');

                return $this->redirectToRoute('admin_appearance_section', $redirectParams);
            }
        }

        return $this->render('settings/appearance.html.twig', [
            'form' => $form,
            'appearanceThemeForm' => $themeForm->createView(),
            'section' => $sectionEnum,
            'subtab' => $subEnum,
            'sections' => AppearanceSettingsSection::cases(),
            'activeThemeIdLight' => AppearanceThemePresets::matchLightId($appearance),
            'activeThemeIdDark' => AppearanceThemePresets::matchDarkId($appearance),
            'lightThemes' => AppearanceThemePresets::byMode('light'),
            'darkThemes' => AppearanceThemePresets::byMode('dark'),
        ]);
    }

    /**
     * @return array{section: string, sub?: string}
     */
    private function sectionParams(AppearanceSettingsSection $section, ?AppearanceSettingsSubtab $sub): array
    {
        $params = ['section' => $section->value];
        if (null !== $sub) {
            $params['sub'] = $sub->value;
        }

        return $params;
    }

    private function resolveSubtab(AppearanceSettingsSection $section, ?string $sub): ?AppearanceSettingsSubtab
    {
        $allowed = $section->subtabs();
        if ([] === $allowed) {
            return null;
        }

        if (null === $sub || '' === $sub) {
            return null;
        }

        $subEnum = AppearanceSettingsSubtab::tryFrom($sub);
        if (null === $subEnum || !\in_array($subEnum, $allowed, true)) {
            throw $this->createNotFoundException();
        }

        return $subEnum;
    }
}
