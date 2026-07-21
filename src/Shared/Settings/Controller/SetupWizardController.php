<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\DemoIdentitySeeder;
use App\Project\Repository\ProjectRepository;
use App\Shared\Breadcrumb\BreadcrumbDemoSeeder;
use App\Shared\CookieConsent\CookieConsentDemoSeeder;
use App\Shared\Locale\LocalizedPublicPath;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Shared\Service\SampleDataService;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\PlatformBootstrapState;
use App\Shared\Settings\Service\SetupWizardAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * First-run setup wizard — public bootstrap when no users exist; ROLE_ADMIN afterwards.
 *
 * Default locale is bare (`/setup`). Other locales use `/{_locale}/setup`.
 * Prefixed URLs for the default locale redirect to the bare path.
 */
final class SetupWizardController extends AbstractController
{
    private const string LOCALE_REQUIREMENT = 'en|es|de|nl|fr|it|pt';

    /** @var list<string> */
    private const BOOTSTRAP_ACTIONS = ['platform', 'sample_load', 'complete'];

    public function __construct(
        private readonly InstanceSettingsRepository $settingsRepository,
        private readonly DashboardMenuDemoSeeder $dashboardMenuDemoSeeder,
        private readonly BreadcrumbDemoSeeder $breadcrumbDemoSeeder,
        private readonly CookieConsentDemoSeeder $cookieConsentDemoSeeder,
        private readonly DemoIdentitySeeder $demoIdentitySeeder,
        private readonly SampleDataService $sampleDataService,
        private readonly ProjectRepository $projectRepository,
        private readonly UserRepository $userRepository,
        private readonly SetupWizardAccess $setupWizardAccess,
        private readonly PlatformBootstrapState $platformBootstrapState,
        private readonly LocalizedPublicPath $localizedPublicPath,
        private readonly string $defaultLocale = 'en',
    ) {
    }

    #[Route(
        '/setup',
        name: LocalizedPublicPath::SETUP,
        methods: ['GET'],
        defaults: ['_locale' => null],
    )]
    public function showBare(Request $request): Response
    {
        $this->applyDefaultLocale($request);

        return $this->renderSetup($request);
    }

    #[Route(
        '/setup/run',
        name: LocalizedPublicPath::SETUP_RUN,
        methods: ['POST'],
        defaults: ['_locale' => null],
    )]
    public function runBare(Request $request): RedirectResponse
    {
        $this->applyDefaultLocale($request);

        return $this->executeRun($request);
    }

    #[Route(
        '/{_locale}/setup',
        name: LocalizedPublicPath::SETUP_LOCALIZED,
        requirements: ['_locale' => self::LOCALE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function showLocalized(Request $request, string $_locale): Response
    {
        if ($this->localizedPublicPath->isDefault($_locale)) {
            return $this->redirectToRoute(LocalizedPublicPath::SETUP);
        }

        return $this->renderSetup($request);
    }

    #[Route(
        '/{_locale}/setup/run',
        name: LocalizedPublicPath::SETUP_RUN_LOCALIZED,
        requirements: ['_locale' => self::LOCALE_REQUIREMENT],
        methods: ['POST'],
    )]
    public function runLocalized(Request $request, string $_locale): RedirectResponse
    {
        if ($this->localizedPublicPath->isDefault($_locale)) {
            return $this->redirectToRoute(LocalizedPublicPath::SETUP_RUN, [], Response::HTTP_TEMPORARY_REDIRECT);
        }

        return $this->executeRun($request);
    }

    private function applyDefaultLocale(Request $request): void
    {
        $request->setLocale($this->defaultLocale);
        $request->attributes->set('_locale', $this->defaultLocale);
    }

    private function renderSetup(Request $request): Response
    {
        if ($redirect = $this->guardAccess()) {
            return $redirect;
        }

        $this->syncGuestSessionLocale($request);

        $locale = $this->requestLocale($request);
        $settings = $this->settingsRepository->getOrCreate();
        $demo = $this->projectRepository->findOneBy(['slug' => 'demo']);
        $needsPlatform = $this->platformBootstrapState->needsPlatformSeed();
        $userCount = $this->userRepository->count([]);

        return $this->render('settings/setup.html.twig', [
            'setupCompleted' => $settings->isSetupCompleted(),
            'hasDemoProject' => null !== $demo,
            'bootstrapMode' => $this->setupWizardAccess->isBootstrapOpen(),
            'needsPlatformSeed' => $needsPlatform,
            'hasUsers' => $userCount > 0,
            'demoEmail' => 'admin@symfony-beacon.local',
            'demoPassword' => 'admin123',
            'setupRunRoute' => $this->localizedPublicPath->setupRunRouteName($locale),
            'setupRouteParams' => $this->localizedPublicPath->routeParams($locale),
        ]);
    }

    private function executeRun(Request $request): RedirectResponse
    {
        if ($redirect = $this->guardAccess()) {
            return $redirect;
        }

        $this->syncGuestSessionLocale($request);

        if (!$this->isCsrfTokenValid('setup_wizard', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $action = (string) $request->request->get('action');
        $bootstrap = $this->setupWizardAccess->isBootstrapOpen();
        $locale = $this->requestLocale($request);
        $setupRoute = $this->localizedPublicPath->setupRouteName($locale);
        $setupParams = $this->localizedPublicPath->routeParams($locale);

        if ($bootstrap && !\in_array($action, self::BOOTSTRAP_ACTIONS, true)) {
            $this->addFlash('error', 'setup.flash.unknown_action');

            return $this->redirectToRoute($setupRoute, $setupParams);
        }

        if ($this->platformBootstrapState->needsPlatformSeed() && 'platform' !== $action) {
            $this->addFlash('error', 'setup.flash.platform_required');

            return $this->redirectToRoute($setupRoute, $setupParams);
        }

        try {
            match ($action) {
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

            return $this->redirectToRoute($setupRoute, $setupParams);
        }

        if ('complete' === $action) {
            if (!$this->getUser()) {
                $this->addFlash('success', 'setup.flash.bootstrap_ready');

                return $this->redirectToRoute('nowo_auth_kit_login');
            }

            return $this->redirectToRoute('dashboard_home');
        }

        return $this->redirectToRoute($setupRoute, $setupParams);
    }

    private function requestLocale(Request $request): string
    {
        $locale = $request->attributes->get('_locale');
        if (!\is_string($locale) || '' === $locale) {
            $locale = $request->getLocale();
        }
        if (!\is_string($locale) || '' === $locale) {
            $locale = $this->defaultLocale;
        }

        return $locale;
    }

    private function syncGuestSessionLocale(Request $request): void
    {
        if ($this->getUser()) {
            return;
        }

        if (!$request->hasSession()) {
            return;
        }

        $request->getSession()->set('_locale', $this->requestLocale($request));
    }

    private function guardAccess(): ?RedirectResponse
    {
        if ($this->setupWizardAccess->canAccess()) {
            return null;
        }

        if (!$this->getUser()) {
            return $this->redirectToRoute('nowo_auth_kit_login');
        }

        throw $this->createAccessDeniedException('Setup wizard is not available.');
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
        $owner = $this->getUser();
        $ownerUser = $owner instanceof User ? $owner : null;
        $ensured = $this->demoIdentitySeeder->ensureDemoProject($ownerUser);
        if ($withFlash && $ensured['user_created']) {
            $this->addFlash('success', 'setup.flash.sample_demo_user');
        }

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
