<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Identity\Entity\User;
use App\Identity\Form\MemberProjectAlertPreferencesType;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Service\MemberAlertPreferenceManager;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Form\ProjectGovernanceType;
use App\Project\Form\ProjectType;
use App\Project\Repository\ProjectRepository;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectFactory;
use App\Project\Service\ProjectGovernanceResolver;
use App\Project\Service\ProjectSettingsPageBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Project create/show and settings (including governance save).
 */
#[IsGranted('ROLE_USER')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly DailyProjectStatRepository $dailyProjectStatRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectGovernanceResolver $governanceResolver,
        private readonly ProjectAccessService $projectAccess,
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectFactory $projectFactory,
        private readonly ProjectSettingsPageBuilder $settingsPageBuilder,
        private readonly MemberAlertPreferenceManager $memberAlertPreferenceManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/projects/new', name: 'project_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod(Request::METHOD_GET)) {
            return $this->redirectToRoute('dashboard_home', ['new' => 1]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProjectType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, description?: string|null} $data */
            $data = $form->getData();
            $name = trim((string) $data['name']);
            $description = trim((string) ($data['description'] ?? ''));

            $project = $this->projectFactory->create($user, $name, '' !== $description ? $description : null);

            $this->projectRepository->save($project);

            $this->userActionRecorder->recordAndFlush(UserActionType::ProjectCreated, $user, $user, [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
            ]);

            $this->addFlash('success', 'flash.project.created');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        return $this->renderDashboardHome($user, $request, $form, openNewProject: true);
    }

    /**
     * @param FormInterface<mixed> $newProjectForm
     */
    private function renderDashboardHome(
        User $user,
        Request $request,
        FormInterface $newProjectForm,
        bool $openNewProject,
    ): Response {
        $query = $request->query->getString('q');
        $projects = $this->projectRepository->findAccessibleByUser($user, '' !== $query ? $query : null);

        $previewProjects = \array_slice($projects, 0, 5);
        $statsPreview = $this->dailyProjectStatRepository->findLastDaysForProjects($previewProjects, 7);

        return $this->render('dashboard/home.html.twig', [
            'projects' => $projects,
            'query' => $query,
            'statsPreview' => $statsPreview,
            'newProjectForm' => $newProjectForm,
            'openNewProject' => $openNewProject,
        ]);
    }

    #[Route('/projects/{id}', name: 'project_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        return $this->redirectToRoute('issue_index', ['id' => $project->getUuid()]);
    }

    #[Route('/projects/{id}/settings', name: 'project_settings', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function settings(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $access = $this->projectAccess->requireSettingsSurface($project, $user);

        $this->userActionRecorder->recordAndFlush(UserActionType::ProjectSettingsViewed, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
        ]);

        $this->maybeFlashApproachingQuota($request, $project, $access);

        return $this->render(
            'project/settings.html.twig',
            $this->settingsPageBuilder->build($project, $user, $access, $request),
        );
    }

    #[Route('/projects/{id}/settings/member-alerts', name: 'project_member_alerts_save', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function saveMemberAlerts(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        // Own member-alert prefs: any project access (viewer+). Settings page UI stays
        // gated by requireSettingsSurface; Account modals use the LiveComponent path.
        $this->projectAccess->requireAccess($project, $user);

        $rows = $this->memberAlertPreferenceManager->projectRowsForUi($user, [$project]);
        $row = $rows[0] ?? null;
        $formData = [
            'enabled' => $row['enabled'] ?? true,
            'resetOverrides' => false,
            'events' => MemberAlertEvent::mapEventsToFormKeys($row['events'] ?? []),
        ];

        $form = $this->formFactory->createNamed(
            MemberProjectAlertPreferencesType::formNameForProject($project),
            MemberProjectAlertPreferencesType::class,
            $formData,
            [
                'action' => $this->generateUrl('project_member_alerts_save', [
                    'id' => $project->getUuid(),
                    'return' => $request->query->getString('return'),
                ]),
                'method' => 'POST',
            ],
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'flash.preferences.member_alerts_invalid');

            return $this->redirectAfterMemberAlertsSave($project, $request);
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData();
        /** @var array<string, mixed> $projectEvents */
        $projectEvents = \is_array($data['events'] ?? null) ? $data['events'] : [];
        $this->memberAlertPreferenceManager->saveProjectPreferences($user, [[
            'project' => $project,
            'enabled' => \array_key_exists('enabled', $data) ? (bool) $data['enabled'] : true,
            'resetOverrides' => (bool) ($data['resetOverrides'] ?? false),
            'events' => MemberAlertEvent::mapEventsFromFormKeys($projectEvents),
        ]]);
        $this->entityManager->flush();
        $this->addFlash('success', 'flash.preferences.member_alerts_project_saved');

        return $this->redirectAfterMemberAlertsSave($project, $request);
    }

    private function redirectAfterMemberAlertsSave(Project $project, Request $request): RedirectResponse
    {
        if ('account' === $request->query->getString('return')) {
            return $this->redirectToRoute('account_display_notifications');
        }

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid(), '_fragment' => 'member-alerts']);
    }

    #[Route('/projects/{id}/governance', name: 'project_governance_save', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function saveGovernance(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requirePermission($project, $user, ProjectPermission::SETTINGS_MANAGE);

        $form = $this->createForm(ProjectGovernanceType::class, null, [
            'csrf_token_id' => 'project_governance_'.$project->getId(),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'flash.project.governance_invalid');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        /** @var array<string, int|null> $data */
        $data = $form->getData();

        $project->setRetentionDays($data['retention_days'] ?? null);
        $project->setRetentionMaxEvents($data['retention_max_events'] ?? null);
        $project->setIngestRateLimitPerMinute($data['ingest_rate_limit_per_minute'] ?? null);
        $project->setEventQuotaDaily($data['event_quota_daily'] ?? null);
        $project->setEventQuotaMonthly($data['event_quota_monthly'] ?? null);
        // ingestEnabled is toggled by platform admins (019); owners keep current value here.
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.governance_saved');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    private function maybeFlashApproachingQuota(Request $request, Project $project, ProjectAccess $access): void
    {
        if (!$access->canManageSettings()) {
            return;
        }

        $session = $request->getSession();
        if ($this->governanceResolver->isApproachingDailyQuota($project)) {
            $flagKey = '_beacon_quota_warn_'.$project->getUuid();
            if (!$session->get($flagKey)) {
                $session->set($flagKey, true);
                $this->addFlash('warning', 'flash.project.quota_approaching');
            }
        }

        if ($this->governanceResolver->isApproachingMonthlyQuota($project)) {
            $flagKey = '_beacon_quota_monthly_warn_'.$project->getUuid();
            if (!$session->get($flagKey)) {
                $session->set($flagKey, true);
                $this->addFlash('warning', 'flash.project.quota_monthly_approaching');
            }
        }
    }
}
