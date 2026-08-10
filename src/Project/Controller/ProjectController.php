<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Form\ProjectType;
use App\Project\Repository\ProjectReadTokenRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Security\ProjectPermission;
use App\Project\Service\HumanFriendlyTokenGenerator;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectFactory;
use App\Project\Service\ProjectGovernanceResolver;
use App\Project\Service\ProjectMembershipManager;
use App\Shared\Health\MessengerQueueHealth;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private readonly NotificationDeliveryAttemptRepository $deliveryAttemptRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectGovernanceResolver $governanceResolver,
        private readonly ProjectMembershipManager $membershipManager,
        private readonly MessengerQueueHealth $messengerQueueHealth,
        private readonly ProjectAccessService $projectAccess,
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectFactory $projectFactory,
        private readonly ProjectReadTokenRepository $readTokenRepository,
        private readonly ProjectShareLinkRepository $shareLinkRepository,
        private readonly HumanFriendlyTokenGenerator $tokenGenerator,
        private readonly UserActionRecorder $userActionRecorder,
        private readonly UserGroupMembershipRepository $userGroupMembershipRepository,
        private readonly UserGroupRepository $userGroupRepository,
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
        $baseUrl = $request->getSchemeAndHttpHost();

        $this->userActionRecorder->recordAndFlush(UserActionType::ProjectSettingsViewed, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
        ]);

        $this->maybeFlashApproachingQuota($request, $project, $access);

        $this->projectRepository->hydrateAccessGraph($project);
        $availableGroups = $this->availableGroupsForProject($project, $user);
        $groupIds = ProjectRepository::collectGroupIds($project, $availableGroups);

        $destinations = $project->getNotificationDestinations()->toArray();

        return $this->render('project/settings.html.twig', [
            'project' => $project,
            'access' => $access,
            'membership' => $access, // BC alias for templates expecting .role
            'baseUrl' => $baseUrl,
            'labelAdjectives' => $this->tokenGenerator->adjectiveWordList(),
            'labelNouns' => $this->tokenGenerator->nounWordList(),
            'suggestedLabel' => $this->tokenGenerator->generateLabel(),
            'assignableRoles' => $this->membershipManager->assignableRoles($user, $project),
            'assignableGroupRoles' => $this->membershipManager->assignableGroupRoles($user, $project),
            'availableGroups' => $availableGroups,
            'group_member_counts' => $this->userGroupMembershipRepository->countByGroupIds($groupIds),
            'delivery_attempts_by_destination' => $this->deliveryAttemptRepository->findRecentByDestinations($destinations),
            'ownerCount' => $this->countOwners($project),
            'transferCandidates' => $this->transferOwnershipCandidates($project, $user),
            'governanceDefaults' => $this->governanceResolver->envDefaults(),
            'eventsToday' => $this->governanceResolver->eventsReceivedToday($project),
            'effectiveQuota' => $this->governanceResolver->effectiveEventQuotaDaily($project),
            'eventsThisMonth' => $this->governanceResolver->eventsReceivedThisMonth($project),
            'effectiveMonthlyQuota' => $this->governanceResolver->effectiveEventQuotaMonthly($project),
            'messengerQueue' => $this->messengerQueueHealth->asyncPending(),
            'shareLinks' => $this->shareLinkRepository->findActiveByProject($project),
            'lastShareUrl' => $request->getSession()->remove('_beacon_last_share_url'),
            'readTokens' => $this->readTokenRepository->findByProject($project),
            'lastReadToken' => $request->getSession()->remove('_beacon_last_read_token'),
            'lastApiKeyDsn' => $request->getSession()->remove('_beacon_last_api_key_dsn'),
        ]);
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

        if (!$this->isCsrfTokenValid('project_governance_'.$project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $retentionDays = $this->parseOptionalNonNegativeInt($request->request->getString('retention_days'));
        $retentionMaxEvents = $this->parseOptionalNonNegativeInt($request->request->getString('retention_max_events'));
        $ingestRateLimit = $this->parseOptionalNonNegativeInt($request->request->getString('ingest_rate_limit_per_minute'));
        $eventQuotaDaily = $this->parseOptionalNonNegativeInt($request->request->getString('event_quota_daily'));
        $eventQuotaMonthly = $this->parseOptionalNonNegativeInt($request->request->getString('event_quota_monthly'));

        if (
            \in_array(false, [$retentionDays, $retentionMaxEvents, $ingestRateLimit, $eventQuotaDaily, $eventQuotaMonthly], true)
        ) {
            $this->addFlash('error', 'flash.project.governance_invalid');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        $project->setRetentionDays($retentionDays);
        $project->setRetentionMaxEvents($retentionMaxEvents);
        $project->setIngestRateLimitPerMinute($ingestRateLimit);
        $project->setEventQuotaDaily($eventQuotaDaily);
        $project->setEventQuotaMonthly($eventQuotaMonthly);
        // ingestEnabled is toggled by platform admins (019); owners keep current value here.
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.governance_saved');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    /**
     * Direct members eligible to receive ownership (everyone except the actor).
     *
     * @return list<ProjectMembership>
     */
    private function transferOwnershipCandidates(Project $project, User $actor): array
    {
        $candidates = [];
        foreach ($project->getMemberships() as $membership) {
            $member = $membership->getUser();
            if (null === $member || $member->getId() === $actor->getId()) {
                continue;
            }
            $candidates[] = $membership;
        }

        return $candidates;
    }

    /**
     * Groups that the actor may link: all (owner / ROLE_ADMIN) or only groups they belong to (project admin).
     *
     * @return list<UserGroup>
     */
    private function availableGroupsForProject(Project $project, User $actor): array
    {
        $linkedIds = [];
        foreach ($project->getGroupAccesses() as $access) {
            $id = $access->getUserGroup()?->getId();
            if (null !== $id) {
                $linkedIds[$id] = true;
            }
        }

        $canLinkAny = $this->isGranted('ROLE_ADMIN');
        if (!$canLinkAny) {
            $access = $this->projectAccess->resolveAccess($project, $actor);
            $canLinkAny = $access instanceof ProjectAccess && ProjectRole::Owner === $access->role;
        }

        /** @var array<int, true> $actorGroupIds */
        $actorGroupIds = [];
        if (!$canLinkAny) {
            foreach ($this->userGroupMembershipRepository->findByUser($actor) as $membership) {
                $gid = $membership->getUserGroup()?->getId();
                if (null !== $gid) {
                    $actorGroupIds[$gid] = true;
                }
            }
        }

        $groups = [];
        foreach ($this->userGroupRepository->findAllOrdered() as $group) {
            $id = $group->getId();
            if (null === $id || isset($linkedIds[$id])) {
                continue;
            }
            if (!$canLinkAny && !isset($actorGroupIds[$id])) {
                continue;
            }
            $groups[] = $group;
        }

        return $groups;
    }

    private function countOwners(Project $project): int
    {
        $count = 0;
        foreach ($project->getMemberships() as $member) {
            if (ProjectRole::Owner === $member->getRole()) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Empty string → null (inherit env). Invalid / negative → false.
     */
    private function parseOptionalNonNegativeInt(string $raw): int|false|null
    {
        $trimmed = trim($raw);
        if ('' === $trimmed) {
            return null;
        }
        if (!ctype_digit($trimmed)) {
            return false;
        }

        return (int) $trimmed;
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
