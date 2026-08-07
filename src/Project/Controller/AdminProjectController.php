<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\AdminAuditFilter;
use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectRole;
use App\Project\Form\ProjectType;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectFactory;
use App\Project\Service\ProjectHistoryClearer;
use App\Project\Service\ProjectMembershipManager;
use App\Project\Service\ProjectOpsStatsService;
use App\Shared\Health\MessengerQueueHealth;
use App\Shared\Http\SafeInternalRedirect;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Instance-admin project list, CRUD, ingest toggle, and view-as-member.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminProjectController extends AbstractController
{
    private const int PROJECT_AUDIT_LIMIT = 100;

    public function __construct(
        private readonly UserActionRecorder $actionRecorder,
        private readonly NotificationDeliveryAttemptRepository $deliveryAttemptRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectHistoryClearer $historyClearer,
        private readonly ProjectMembershipManager $membershipManager,
        private readonly ProjectMembershipRepository $membershipRepository,
        private readonly MessengerQueueHealth $messengerQueueHealth,
        private readonly ProjectOpsStatsService $opsStats,
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectFactory $projectFactory,
        private readonly UserActionRepository $userActionRepository,
        private readonly UserGroupMembershipRepository $userGroupMembershipRepository,
        private readonly UserGroupRepository $userGroupRepository,
    ) {
    }

    /** List all projects on the instance (optional name/slug search). */
    #[Route('/admin/projects', name: 'admin_projects', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $request->query->getString('q');
        $projects = $this->projectRepository->findAllOrdered('' !== $query ? $query : null);
        $projectIds = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            if (null !== $id) {
                $projectIds[] = $id;
            }
        }

        return $this->render('admin/projects/index.html.twig', [
            'projects' => $projects,
            'q' => $query,
            'opsStats' => $this->opsStats->forProjects($projects),
            'access_counts' => $this->projectRepository->countAccessByProjectIds($projectIds),
        ]);
    }

    /** Create a project (admin becomes owner; default API key). */
    #[Route('/admin/projects/new', name: 'admin_projects_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(ProjectType::class, [
            'name' => '',
            'description' => '',
        ], [
            'csrf_token_id' => 'admin_project_new',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, description?: string|null} $data */
            $data = $form->getData();
            /** @var User $actor */
            $actor = $this->getUser();
            $project = $this->createProject(
                trim($data['name']),
                (string) ($data['description'] ?? ''),
                $actor,
            );
            $this->addFlash('success', 'flash.project.created');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        return $this->render('admin/projects/form.html.twig', [
            'form' => $form,
            'project' => null,
            'is_edit' => false,
        ]);
    }

    /** Project detail: members, linked groups, open in product UI, delete. */
    #[Route('/admin/projects/{id}', name: 'admin_projects_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
        /** @var User $actor */
        $actor = $this->getUser();
        $auditActions = $this->projectAuditActionTypes();
        $audit = AdminAuditFilter::fromRequest($request, $auditActions);

        $this->projectRepository->hydrateAccessGraph($project);
        $availableGroups = $this->availableGroups($project);
        $groupIds = ProjectRepository::collectGroupIds($project, $availableGroups);
        $destinations = $project->getNotificationDestinations()->toArray();

        return $this->render('admin/projects/show.html.twig', [
            'project' => $project,
            'assignableRoles' => $this->membershipManager->assignableRoles($actor, $project),
            'assignableGroupRoles' => $this->membershipManager->assignableGroupRoles($actor, $project),
            'availableGroups' => $availableGroups,
            'group_member_counts' => $this->userGroupMembershipRepository->countByGroupIds($groupIds),
            'delivery_attempts_by_destination' => $this->deliveryAttemptRepository->findRecentByDestinations($destinations),
            'ownerCount' => $this->countOwners($project),
            'opsStats' => $this->opsStats->forProject($project),
            'messengerQueue' => $this->messengerQueueHealth->asyncPending(),
            'projectAuditActions' => $auditActions,
            'projectAuditFilter' => $audit['filter'],
            'projectAuditEntries' => $this->userActionRepository->findForProject(
                $project,
                $auditActions,
                $audit['action'],
                $audit['from'],
                $audit['to'],
                self::PROJECT_AUDIT_LIMIT,
            ),
        ]);
    }

    /** Suspend or resume Envelope ingest for a project. */
    #[Route('/admin/projects/{id}/ingest', name: 'admin_projects_ingest_toggle', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function toggleIngest(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_project_ingest_'.$project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $actor */
        $actor = $this->getUser();
        $enable = '1' === $request->request->getString('enabled');
        $project->setIngestEnabled($enable);
        $this->actionRecorder->record(
            $enable ? UserActionType::ProjectResumed : UserActionType::ProjectSuspended,
            $actor,
            $actor,
            [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
            ],
        );
        $this->entityManager->flush();
        $this->addFlash('success', $enable ? 'flash.admin_projects.ingest_resumed' : 'flash.admin_projects.ingest_suspended');

        return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
    }

    /** Enter view-as-member mode (ROLE_ADMIN effective role forced to Member). */
    #[Route('/admin/view-as-member/enable', name: 'admin_view_as_member_enable', methods: ['POST'])]
    public function enableViewAsMember(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_view_as_member_enable', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $actor */
        $actor = $this->getUser();
        $request->getSession()->set(ProjectAccessService::VIEW_AS_MEMBER_SESSION_KEY, true);
        $context = [];
        $projectUuid = trim($request->request->getString('project_uuid'));
        if ('' !== $projectUuid) {
            $project = $this->projectRepository->findOneBy(['uuid' => $projectUuid]);
            if ($project instanceof Project) {
                $context = [
                    'project_uuid' => $project->getUuid(),
                    'project_name' => $project->getName(),
                ];
            }
        }

        $this->actionRecorder->recordAndFlush(UserActionType::ProjectViewAsStarted, $actor, $actor, $context);
        $this->addFlash('success', 'flash.admin_projects.view_as_enabled');

        $fallback = $this->generateUrl('admin_projects');

        return $this->redirect(SafeInternalRedirect::resolve($request, $request->request->getString('redirect'), $fallback));
    }

    /** Exit view-as-member mode. */
    #[Route('/admin/view-as-member/disable', name: 'admin_view_as_member_disable', methods: ['POST'])]
    public function disableViewAsMember(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_view_as_member_disable', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $actor */
        $actor = $this->getUser();
        $request->getSession()->remove(ProjectAccessService::VIEW_AS_MEMBER_SESSION_KEY);
        $this->actionRecorder->recordAndFlush(UserActionType::ProjectViewAsEnded, $actor, $actor, []);
        $this->addFlash('success', 'flash.admin_projects.view_as_disabled');

        $fallback = $this->generateUrl('admin_projects');

        return $this->redirect(SafeInternalRedirect::resolve($request, $request->request->getString('redirect'), $fallback));
    }

    /** Update project name and description. */
    #[Route('/admin/projects/{id}/edit', name: 'admin_projects_edit', requirements: ['id' => Requirement::UUID], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
        $form = $this->createForm(ProjectType::class, [
            'name' => $project->getName(),
            'description' => $project->getDescription() ?? '',
        ], [
            'csrf_token_id' => 'admin_project_edit_'.$project->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, description?: string|null} $data */
            $data = $form->getData();
            $project->setName(trim($data['name']));
            $description = trim((string) ($data['description'] ?? ''));
            $project->setDescription('' !== $description ? $description : null);
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.admin_projects.updated');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        return $this->render('admin/projects/form.html.twig', [
            'form' => $form,
            'project' => $project,
            'is_edit' => true,
        ]);
    }

    /** Permanently delete a project (typed name confirmation; clears telemetry first). */
    #[Route('/admin/projects/{id}/delete', name: 'admin_projects_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_project_delete_'.$project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $confirmation = $request->request->getString('confirmation');
        if ($confirmation !== $project->getName()) {
            $this->addFlash('error', 'flash.project.delete_confirmation_mismatch');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        /** @var User $actor */
        $actor = $this->getUser();
        $projectUuid = $project->getUuid();
        $projectName = $project->getName();
        $projectId = $project->getId();
        $actorId = $actor->getId();

        $this->historyClearer->clear($project);

        $managedActor = null !== $actorId
            ? $this->entityManager->find(User::class, $actorId)
            : null;
        $project = null !== $projectId
            ? $this->projectRepository->find($projectId)
            : null;

        $this->actionRecorder->record(
            UserActionType::ProjectDeleted,
            $managedActor,
            $managedActor,
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

        return $this->redirectToRoute('admin_projects');
    }

    private function createProject(string $name, string $description, User $owner): Project
    {
        $project = $this->projectFactory->create($owner, $name, '' !== trim($description) ? trim($description) : null);

        $this->projectRepository->save($project, false);
        $this->actionRecorder->record(
            UserActionType::ProjectCreated,
            $owner,
            $owner,
            [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
            ],
        );
        $this->entityManager->flush();

        return $project;
    }

    /** @return list<UserGroup> */
    private function availableGroups(Project $project): array
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
            $groups[] = $group;
        }

        return $groups;
    }

    private function countOwners(Project $project): int
    {
        return $this->membershipRepository->count([
            'project' => $project,
            'role' => ProjectRole::Owner,
        ]);
    }

    /**
     * Administrative project actions shown on Admin -> Project audit timeline.
     *
     * @return list<UserActionType>
     */
    private function projectAuditActionTypes(): array
    {
        return [
            UserActionType::ProjectCreated,
            UserActionType::ProjectMemberAdded,
            UserActionType::ProjectMemberRoleChanged,
            UserActionType::ProjectMemberRemoved,
            UserActionType::ProjectOwnershipTransferred,
            UserActionType::ProjectGroupLinked,
            UserActionType::ProjectGroupRoleChanged,
            UserActionType::ProjectGroupUnlinked,
            UserActionType::ProjectApiKeyCreated,
            UserActionType::ProjectApiKeyRevoked,
            UserActionType::ProjectApiKeyRotated,
            UserActionType::ProjectSuspended,
            UserActionType::ProjectResumed,
            UserActionType::ProjectViewAsStarted,
            UserActionType::ProjectHistoryCleared,
            UserActionType::ProjectDeleted,
        ];
    }
}
