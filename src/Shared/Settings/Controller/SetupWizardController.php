<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use App\Identity\Service\DemoIdentitySeeder;
use App\Project\Repository\ProjectRepository;
use App\Shared\Breadcrumb\BreadcrumbDemoSeeder;
use App\Shared\CookieConsent\CookieConsentDemoSeeder;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Shared\Service\SampleDataService;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\SetupWizardAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * First-run setup wizard — public bootstrap when no users exist; ROLE_ADMIN afterwards.
 */
final class SetupWizardController extends AbstractController
{
    public function __construct(
        private readonly InstanceSettingsRepository $settingsRepository,
        private readonly DashboardMenuDemoSeeder $dashboardMenuDemoSeeder,
        private readonly BreadcrumbDemoSeeder $breadcrumbDemoSeeder,
        private readonly CookieConsentDemoSeeder $cookieConsentDemoSeeder,
        private readonly DemoIdentitySeeder $demoIdentitySeeder,
        private readonly SampleDataService $sampleDataService,
        private readonly ProjectRepository $projectRepository,
        private readonly SetupWizardAccess $setupWizardAccess,
    ) {
    }

    #[Route('/setup', name: 'setup_wizard', methods: ['GET'])]
    public function show(): Response
    {
        if ($redirect = $this->guardAccess()) {
            return $redirect;
        }

        $settings = $this->settingsRepository->getOrCreate();
        $demo = $this->projectRepository->findOneBy(['slug' => 'demo']);
        $bootstrap = $this->setupWizardAccess->isBootstrapOpen();

        return $this->render('settings/setup.html.twig', [
            'setupCompleted' => $settings->isSetupCompleted(),
            'hasDemoProject' => null !== $demo,
            'bootstrapMode' => $bootstrap,
            'demoEmail' => 'admin@symfony-beacon.local',
            'demoPassword' => 'admin123',
        ]);
    }

    #[Route('/setup/run', name: 'setup_wizard_run', methods: ['POST'])]
    public function run(Request $request): RedirectResponse
    {
        if ($redirect = $this->guardAccess()) {
            return $redirect;
        }

        if (!$this->isCsrfTokenValid('setup_wizard', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $action = (string) $request->request->get('action');
        $bootstrap = $this->setupWizardAccess->isBootstrapOpen();

        if ($bootstrap && !\in_array($action, ['minimum', 'bulk'], true)) {
            $this->addFlash('error', 'setup.flash.unknown_action');

            return $this->redirectToRoute('setup_wizard');
        }

        try {
            match ($action) {
                'minimum' => $this->runMinimum(),
                'bulk' => $this->runBulk(),
                'platform' => $this->runPlatform(withFlash: true),
                'demo' => $this->runDemo(withFlash: true),
                'sample_dev' => $this->runSample('dev', withFlash: true),
                'sample_load' => $this->runSample('load', withFlash: true),
                'complete' => $this->completeSetup(withFlash: true),
                default => $this->addFlash('error', 'setup.flash.unknown_action'),
            };
        } catch (Throwable $e) {
            $this->addFlash('error', 'setup.flash.failed');
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('setup_wizard');
        }

        if (\in_array($action, ['minimum', 'bulk'], true) && !$this->getUser()) {
            $this->addFlash('success', 'setup.flash.bootstrap_ready');

            return $this->redirectToRoute('nowo_auth_kit_login', ['_locale' => 'en']);
        }

        return $this->redirectToRoute('setup_wizard');
    }

    private function guardAccess(): ?RedirectResponse
    {
        if ($this->setupWizardAccess->canAccess()) {
            return null;
        }

        if (!$this->getUser()) {
            return $this->redirectToRoute('nowo_auth_kit_login', ['_locale' => 'en']);
        }

        throw $this->createAccessDeniedException('Setup wizard is not available.');
    }

    private function runMinimum(): void
    {
        $this->runPlatform(withFlash: false);
        $this->runDemo(withFlash: false);
        $this->completeSetup(withFlash: false);
        if ($this->getUser()) {
            $this->addFlash('success', 'setup.flash.minimum_ok');
        }
    }

    private function runBulk(): void
    {
        $this->runPlatform(withFlash: false);
        $this->runDemo(withFlash: false);
        $this->runSample('load', withFlash: false);
        $this->completeSetup(withFlash: false);
        if ($this->getUser()) {
            $this->addFlash('success', 'setup.flash.bulk_ok');
        }
    }

    private function runPlatform(bool $withFlash): void
    {
        $this->breadcrumbDemoSeeder->seedIfEmpty();
        $this->dashboardMenuDemoSeeder->seedIfEmpty();
        $this->cookieConsentDemoSeeder->seedIfEmpty();
        if ($withFlash) {
            $this->addFlash('success', 'setup.flash.platform_ok');
        }
    }

    private function runDemo(bool $withFlash): void
    {
        $result = $this->demoIdentitySeeder->seed();
        if (!$withFlash) {
            return;
        }
        if ($result['user_created'] || $result['project_created']) {
            $this->addFlash('success', 'setup.flash.demo_ok');
        } else {
            $this->addFlash('success', 'setup.flash.demo_exists');
        }
    }

    private function runSample(string $size, bool $withFlash): void
    {
        $project = $this->sampleDataService->resolveProject('demo');
        $this->sampleDataService->seed($project, $size);
        if ($withFlash) {
            $this->addFlash('success', 'setup.flash.sample_ok');
        }
    }

    private function completeSetup(bool $withFlash): void
    {
        $settings = $this->settingsRepository->getOrCreate();
        $settings->markSetupCompleted();
        $this->settingsRepository->save($settings);
        if ($withFlash) {
            $this->addFlash('success', 'setup.flash.complete');
        }
    }
}
