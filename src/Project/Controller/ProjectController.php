<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Identity\Entity\User;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectMembership;
use App\Project\Form\ProjectType;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\HumanFriendlyTokenGenerator;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectGovernanceResolver;
use App\Project\Service\ProjectHistoryClearer;
use App\Project\Service\ProjectMembershipManager;
use App\Shared\Health\MessengerQueueHealth;
use App\Shared\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Project CRUD, Settings (keys/members), and danger-zone clear/delete actions.
 */
#[IsGranted('ROLE_USER')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectAccessService $projectAccess,
        private readonly ProjectHistoryClearer $historyClearer,
        private readonly ProjectMembershipManager $membershipManager,
        private readonly ProjectGovernanceResolver $governanceResolver,
        private readonly UserGroupRepository $userGroupRepository,
        private readonly HumanFriendlyTokenGenerator $tokenGenerator,
        private readonly UserActionRecorder $userActionRecorder,
        private readonly DailyProjectStatRepository $dailyProjectStatRepository,
        private readonly MessengerQueueHealth $messengerQueueHealth,
        private readonly EntityManagerInterface $entityManager,
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

            $slugger = new AsciiSlugger();
            $slug = strtolower($slugger->slug($name)->toString());
            if ('' === $slug) {
                $slug = 'project-'.bin2hex(random_bytes(3));
            }
            if (null !== $this->projectRepository->findOneBy(['slug' => $slug])) {
                $slug .= '-'.bin2hex(random_bytes(2));
            }

            $project = new Project();
            $project->setName($name);
            $project->setSlug($slug);
            $project->setDescription('' !== $description ? $description : null);

            $membership = new ProjectMembership();
            $membership->setUser($user);
            $membership->setRole(ProjectRole::Owner);
            $project->addMembership($membership);

            $apiKey = $this->createApiKey($project, 'Default');
            $project->addApiKey($apiKey);

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

        $statsPreview = [];
        foreach (\array_slice($projects, 0, 5) as $project) {
            $statsPreview[$project->getId() ?? 0] = $this->dailyProjectStatRepository->findLastDays($project, 7);
        }

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
        $access = $this->projectAccess->requireMembership($project, $user);
        $baseUrl = $request->getSchemeAndHttpHost();

        $this->userActionRecorder->recordAndFlush(UserActionType::ProjectSettingsViewed, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
        ]);

        $this->maybeFlashApproachingQuota($request, $project, $access);

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
            'availableGroups' => $this->availableGroupsForProject($project, $user),
            'ownerCount' => $this->countOwners($project),
            'transferCandidates' => $this->transferOwnershipCandidates($project, $user),
            'governanceDefaults' => $this->governanceResolver->envDefaults(),
            'eventsToday' => $this->governanceResolver->eventsReceivedToday($project),
            'effectiveQuota' => $this->governanceResolver->effectiveEventQuotaDaily($project),
            'messengerQueue' => $this->messengerQueueHealth->asyncPending(),
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
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        if (!$this->isCsrfTokenValid('project_governance_'.$project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $retentionDays = $this->parseOptionalNonNegativeInt($request->request->getString('retention_days'));
        $retentionMaxEvents = $this->parseOptionalNonNegativeInt($request->request->getString('retention_max_events'));
        $ingestRateLimit = $this->parseOptionalNonNegativeInt($request->request->getString('ingest_rate_limit_per_minute'));
        $eventQuotaDaily = $this->parseOptionalNonNegativeInt($request->request->getString('event_quota_daily'));

        if (
            false === $retentionDays
            || false === $retentionMaxEvents
            || false === $ingestRateLimit
            || false === $eventQuotaDaily
        ) {
            $this->addFlash('error', 'flash.project.governance_invalid');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        $project->setRetentionDays($retentionDays);
        $project->setRetentionMaxEvents($retentionMaxEvents);
        $project->setIngestRateLimitPerMinute($ingestRateLimit);
        $project->setEventQuotaDaily($eventQuotaDaily);
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
     * @return list<\App\Identity\Entity\UserGroup>
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

        $groups = [];
        foreach ($this->userGroupRepository->findAllOrdered() as $group) {
            $id = $group->getId();
            if (null === $id || isset($linkedIds[$id])) {
                continue;
            }
            try {
                $this->membershipManager->assertActorCanLinkGroup($actor, $group, $project);
            } catch (RuntimeException) {
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

    #[Route('/projects/{id}/keys', name: 'project_keys_create', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function createKey(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        if (!$this->isCsrfTokenValid('project_key_create_'.$project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $label = trim($request->request->getString('label'));
        if ('' === $label) {
            $label = $this->tokenGenerator->generateLabel();
        }
        $key = $this->createApiKey($project, $label);
        $project->addApiKey($key);
        $this->userActionRecorder->record(UserActionType::ProjectApiKeyCreated, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'label' => $label,
        ]);
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.api_key_created');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    #[Route(
        '/projects/{projectId}/keys/{keyId}/revoke',
        name: 'project_keys_revoke',
        requirements: ['projectId' => Requirement::UUID, 'keyId' => '\d+'],
        methods: ['POST'],
    )]
    public function revokeKey(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(id: 'keyId')]
        ProjectApiKey $apiKey,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);
        $this->assertKeyBelongsToProject($apiKey, $project);

        if (!$this->isCsrfTokenValid('project_key_revoke_'.$apiKey->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $apiKey->setActive(false);
        $this->userActionRecorder->record(UserActionType::ProjectApiKeyRevoked, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'label' => $apiKey->getLabel(),
        ]);
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.api_key_revoked');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    #[Route(
        '/projects/{projectId}/keys/{keyId}/rotate',
        name: 'project_keys_rotate',
        requirements: ['projectId' => Requirement::UUID, 'keyId' => '\d+'],
        methods: ['POST'],
    )]
    public function rotateKey(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(id: 'keyId')]
        ProjectApiKey $apiKey,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);
        $this->assertKeyBelongsToProject($apiKey, $project);

        if (!$this->isCsrfTokenValid('project_key_rotate_'.$apiKey->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $label = $apiKey->getLabel();
        $apiKey->setActive(false);
        $newKey = $this->createApiKey($project, $label);
        $project->addApiKey($newKey);
        $this->userActionRecorder->record(UserActionType::ProjectApiKeyRotated, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'label' => $label,
        ]);
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.api_key_rotated');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    private function assertKeyBelongsToProject(ProjectApiKey $apiKey, Project $project): void
    {
        if ($apiKey->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException();
        }
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
        if (!\in_array($access->role, [ProjectRole::Owner, ProjectRole::Admin], true)) {
            return;
        }
        if (!$this->governanceResolver->isApproachingDailyQuota($project)) {
            return;
        }

        $session = $request->getSession();
        $flagKey = '_beacon_quota_warn_'.$project->getUuid();
        if ($session->get($flagKey)) {
            return;
        }
        $session->set($flagKey, true);
        $this->addFlash('warning', 'flash.project.quota_approaching');
    }

    #[Route('/projects/{id}/clear-history', name: 'project_clear_history', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function clearHistory(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        if (!$this->isCsrfTokenValid('project_clear_'.$project->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $projectUuid = $project->getUuid();
        $projectName = $project->getName();
        $userId = $user->getId();
        $this->historyClearer->clear($project);

        $managedUser = null !== $userId
            ? $this->entityManager->find(User::class, $userId)
            : null;
        $this->userActionRecorder->recordAndFlush(
            UserActionType::ProjectHistoryCleared,
            $managedUser,
            $managedUser,
            [
                'project_uuid' => $projectUuid,
                'project_name' => $projectName,
            ],
        );

        $this->addFlash('success', 'flash.project.history_cleared');

        return $this->redirectToRoute('project_settings', ['id' => $projectUuid]);
    }

    #[Route('/projects/{id}/delete', name: 'project_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Owner);

        if (!$this->isCsrfTokenValid('project_delete_'.$project->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $confirmation = (string) $request->request->get('confirmation');
        if ($confirmation !== $project->getName()) {
            $this->addFlash('error', 'flash.project.delete_confirmation_mismatch');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        // Clear telemetry first so SQLite (and ORM) stay consistent without relying on DB cascades alone.
        $projectUuid = $project->getUuid();
        $projectName = $project->getName();
        $projectId = $project->getId();
        $userId = $user->getId();
        $this->historyClearer->clear($project);

        $managedUser = null !== $userId
            ? $this->entityManager->find(User::class, $userId)
            : null;
        $project = null !== $projectId
            ? $this->projectRepository->find($projectId)
            : null;

        $this->userActionRecorder->record(
            UserActionType::ProjectDeleted,
            $managedUser,
            $managedUser,
            [
                'project_uuid' => $projectUuid,
                'project_name' => $projectName,
            ],
        );

        if ($project instanceof Project) {
            $this->entityManager->remove($project);
        }
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.deleted');

        return $this->redirectToRoute('dashboard_home');
    }

    private function createApiKey(Project $project, string $label): ProjectApiKey
    {
        for ($attempt = 0; $attempt < 8; ++$attempt) {
            $publicKey = $this->tokenGenerator->generateKey();
            if (null === $this->entityManager->getRepository(ProjectApiKey::class)->findOneBy(['publicKey' => $publicKey])) {
                return ProjectApiKey::generate($project, $label, $publicKey);
            }
        }

        return ProjectApiKey::generate($project, $label, $this->tokenGenerator->generateKey(4));
    }
}
