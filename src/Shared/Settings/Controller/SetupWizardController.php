<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Identity\Service\DemoIdentitySeeder;
use App\Project\Repository\ProjectRepository;
use App\Shared\Breadcrumb\BreadcrumbDemoSeeder;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Shared\Service\SampleDataService;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * First-run setup wizard (ROLE_ADMIN) — orchestrates CLI seed layers from the UI.
 */
#[IsGranted('ROLE_ADMIN')]
final class SetupWizardController extends AbstractController
{
    public function __construct(
        private readonly InstanceSettingsRepository $settingsRepository,
        private readonly DashboardMenuDemoSeeder $dashboardMenuDemoSeeder,
        private readonly BreadcrumbDemoSeeder $breadcrumbDemoSeeder,
        private readonly DemoIdentitySeeder $demoIdentitySeeder,
        private readonly SampleDataService $sampleDataService,
        private readonly ProjectRepository $projectRepository,
    ) {
    }

    #[Route('/setup', name: 'setup_wizard', methods: ['GET'])]
    public function show(): Response
    {
        $settings = $this->settingsRepository->getOrCreate();
        $demo = $this->projectRepository->findOneBy(['slug' => 'demo']);

        return $this->render('settings/setup.html.twig', [
            'setupCompleted' => $settings->isSetupCompleted(),
            'hasDemoProject' => null !== $demo,
        ]);
    }

    #[Route('/setup/run', name: 'setup_wizard_run', methods: ['POST'])]
    public function run(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('setup_wizard', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $action = (string) $request->request->get('action');

        try {
            match ($action) {
                'platform' => $this->runPlatform(),
                'demo' => $this->runDemo(),
                'sample_dev' => $this->runSample('dev'),
                'sample_load' => $this->runSample('load'),
                'complete' => $this->completeSetup(),
                default => $this->addFlash('error', 'setup.flash.unknown_action'),
            };
        } catch (Throwable $e) {
            $this->addFlash('error', 'setup.flash.failed');
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('setup_wizard');
    }

    private function runPlatform(): void
    {
        $this->breadcrumbDemoSeeder->seedIfEmpty();
        $this->dashboardMenuDemoSeeder->seedIfEmpty();
        $this->addFlash('success', 'setup.flash.platform_ok');
    }

    private function runDemo(): void
    {
        $result = $this->demoIdentitySeeder->seed();
        if ($result['user_created'] || $result['project_created']) {
            $this->addFlash('success', 'setup.flash.demo_ok');
        } else {
            $this->addFlash('success', 'setup.flash.demo_exists');
        }
    }

    private function runSample(string $size): void
    {
        $project = $this->sampleDataService->resolveProject('demo');
        $this->sampleDataService->seed($project, $size);
        $this->addFlash('success', 'setup.flash.sample_ok');
    }

    private function completeSetup(): void
    {
        $settings = $this->settingsRepository->getOrCreate();
        $settings->markSetupCompleted();
        $this->settingsRepository->save($settings);
        $this->addFlash('success', 'setup.flash.complete');
    }
}
