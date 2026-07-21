<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\HumanFriendlyTokenGenerator;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectHistoryClearer;
use App\Project\Service\ProjectMembershipManager;
use App\Project\Service\ProjectOpsStatsService;
use App\Shared\Health\MessengerQueueHealth;
use App\Shared\Http\SafeInternalRedirect;
use App\Shared\ProjectRole;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Instance-admin project management: list, create/edit, members, group links, delete.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminProjectController extends AbstractController
{
    private const int PROJECT_AUDIT_LIMIT = 100;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly UserGroupRepository $userGroupRepository,
        private readonly UserGroupMembershipRepository $userGroupMembershipRepository,
        private readonly NotificationDeliveryAttemptRepository $deliveryAttemptRepository,
        private readonly UserActionRepository $userActionRepository,
        private readonly ProjectMembershipManager $membershipManager,
        private readonly ProjectHistoryClearer $historyClearer,
        private readonly ProjectOpsStatsService $opsStats,
        private readonly MessengerQueueHealth $messengerQueueHealth,
        private readonly HumanFriendlyTokenGenerator $tokenGenerator,
        private readonly UserActionRecorder $actionRecorder,
        private readonly EntityManagerInterface $entityManager,
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
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_project_new', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $name = trim($request->request->getString('name'));
            if ('' === $name) {
                $this->addFlash('error', 'flash.admin_projects.name_required');

                return $this->redirectToRoute('admin_projects_new');
            }

            /** @var User $actor */
            $actor = $this->getUser();
            $project = $this->createProject($name, $request->request->getString('description'), $actor);
            $this->addFlash('success', 'flash.project.created');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        return $this->render('admin/projects/form.html.twig', [
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
        $actionFilter = $this->resolveProjectAuditAction($request->query->getString('action'));
        $fromFilter = $this->parseProjectAuditDate($request->query->getString('from'));
        $toFilter = $this->parseProjectAuditDate($request->query->getString('to'), true);

        $this->projectRepository->hydrateAccessGraph($project);
        $availableGroups = $this->availableGroups($project);
        $groupIds = [];
        foreach ($project->getGroupAccesses() as $accessRow) {
            $groupId = $accessRow->getUserGroup()?->getId();
            if (null !== $groupId) {
                $groupIds[] = $groupId;
            }
        }
        foreach ($availableGroups as $group) {
            $groupId = $group->getId();
            if (null !== $groupId) {
                $groupIds[] = $groupId;
            }
        }
        $groupIds = array_values(array_unique($groupIds));
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
            'projectAuditActions' => $this->projectAuditActionTypes(),
            'projectAuditFilter' => [
                'action' => $actionFilter instanceof UserActionType ? $actionFilter->value : '',
                'from' => $request->query->getString('from'),
                'to' => $request->query->getString('to'),
            ],
            'projectAuditEntries' => $this->userActionRepository->findForProject(
                $project,
                $this->projectAuditActionTypes(),
                $actionFilter,
                $fromFilter,
                $toFilter,
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
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_project_edit_'.$project->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $name = trim($request->request->getString('name'));
            if ('' === $name) {
                $this->addFlash('error', 'flash.admin_projects.name_required');

                return $this->redirectToRoute('admin_projects_edit', ['id' => $project->getUuid()]);
            }

            $project->setName($name);
            $description = trim($request->request->getString('description'));
            $project->setDescription('' !== $description ? $description : null);
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.admin_projects.updated');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        return $this->render('admin/projects/form.html.twig', [
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

    /** Add a direct member by email. */
    #[Route('/admin/projects/{id}/members', name: 'admin_projects_members_add', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function addMember(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_project_member_add_'.$project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $actor */
        $actor = $this->getUser();
        $role = ProjectRole::tryFrom($request->request->getString('role')) ?? ProjectRole::Member;

        try {
            $this->membershipManager->addByEmail($project, $actor, $request->request->getString('email'), $role);
            $this->addFlash('success', 'flash.project.member_added');
        } catch (RuntimeException|InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        }

        return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
    }

    /** Change a direct member's role. */
    #[Route(
        '/admin/projects/{projectId}/members/{userId}/role',
        name: 'admin_projects_members_role',
        requirements: ['projectId' => Requirement::UUID, 'userId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function changeMemberRole(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['userId' => 'uuid'])]
        User $memberUser,
        Request $request,
    ): RedirectResponse {
        $target = $this->requireDirectMembership($project, $memberUser);
        if (!$this->isCsrfTokenValid('admin_project_member_role_'.$target->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $role = ProjectRole::tryFrom($request->request->getString('role'));
        if (!$role instanceof ProjectRole) {
            $this->addFlash('error', 'flash.project.member_invalid_role');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        /** @var User $actor */
        $actor = $this->getUser();
        try {
            $this->membershipManager->changeRole($project, $actor, $target, $role);
            $this->addFlash('success', 'flash.project.member_role_updated');
        } catch (RuntimeException|InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        }

        return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
    }

    /** Remove a direct membership. */
    #[Route(
        '/admin/projects/{projectId}/members/{userId}/remove',
        name: 'admin_projects_members_remove',
        requirements: ['projectId' => Requirement::UUID, 'userId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function removeMember(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['userId' => 'uuid'])]
        User $memberUser,
        Request $request,
    ): RedirectResponse {
        $target = $this->requireDirectMembership($project, $memberUser);
        if (!$this->isCsrfTokenValid('admin_project_member_remove_'.$target->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $actor */
        $actor = $this->getUser();
        try {
            $this->membershipManager->remove($project, $actor, $target);
            $this->addFlash('success', 'flash.project.member_removed');
        } catch (RuntimeException|InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        }

        return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
    }

    /** Link a user group to the project. */
    #[Route('/admin/projects/{id}/groups', name: 'admin_projects_groups_add', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function addGroup(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_project_group_add_'.$project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $group = $this->userGroupRepository->findOneBy(['uuid' => $request->request->getString('group')]);
        if (!$group instanceof UserGroup) {
            $this->addFlash('error', 'flash.project.group_not_found');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        $role = ProjectRole::tryFrom($request->request->getString('role')) ?? ProjectRole::Member;
        /** @var User $actor */
        $actor = $this->getUser();
        try {
            $this->membershipManager->addGroup($project, $actor, $group, $role);
            $this->addFlash('success', 'flash.project.group_added');
        } catch (RuntimeException|InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        }

        return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
    }

    /** Change the role of a linked group. */
    #[Route(
        '/admin/projects/{projectId}/groups/{groupAccessId}/role',
        name: 'admin_projects_groups_role',
        requirements: ['projectId' => Requirement::UUID, 'groupAccessId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function changeGroupRole(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['groupAccessId' => 'uuid'])]
        ProjectGroupAccess $groupAccess,
        Request $request,
    ): RedirectResponse {
        if ($groupAccess->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('admin_project_group_role_'.$groupAccess->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $role = ProjectRole::tryFrom($request->request->getString('role'));
        if (!$role instanceof ProjectRole) {
            $this->addFlash('error', 'flash.project.member_invalid_role');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        /** @var User $actor */
        $actor = $this->getUser();
        try {
            $this->membershipManager->changeGroupRole($project, $actor, $groupAccess, $role);
            $this->addFlash('success', 'flash.project.group_role_updated');
        } catch (RuntimeException|InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        }

        return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
    }

    /** Unlink a group from the project. */
    #[Route(
        '/admin/projects/{projectId}/groups/{groupAccessId}/remove',
        name: 'admin_projects_groups_remove',
        requirements: ['projectId' => Requirement::UUID, 'groupAccessId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function removeGroup(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['groupAccessId' => 'uuid'])]
        ProjectGroupAccess $groupAccess,
        Request $request,
    ): RedirectResponse {
        if ($groupAccess->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('admin_project_group_remove_'.$groupAccess->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $actor */
        $actor = $this->getUser();
        try {
            $this->membershipManager->removeGroup($project, $actor, $groupAccess);
            $this->addFlash('success', 'flash.project.group_removed');
        } catch (RuntimeException|InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        }

        return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
    }

    private function createProject(string $name, string $description, User $owner): Project
    {
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
        $trimmed = trim($description);
        $project->setDescription('' !== $trimmed ? $trimmed : null);

        $membership = new ProjectMembership();
        $membership->setUser($owner);
        $membership->setRole(ProjectRole::Owner);
        $project->addMembership($membership);

        $apiKey = $this->createApiKey($project, 'Default');
        $project->addApiKey($apiKey);

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
        $count = 0;
        foreach ($project->getMemberships() as $membership) {
            if (ProjectRole::Owner === $membership->getRole()) {
                ++$count;
            }
        }

        return $count;
    }

    private function requireDirectMembership(Project $project, User $user): ProjectMembership
    {
        foreach ($project->getMemberships() as $membership) {
            if ($membership->getUser()->getId() === $user->getId()) {
                return $membership;
            }
        }

        throw $this->createNotFoundException();
    }

    private function flashKeyForCode(string $code): string
    {
        return match ($code) {
            'user_not_found' => 'flash.project.member_user_not_found',
            'user_disabled' => 'flash.project.member_user_disabled',
            'already_member' => 'flash.project.member_already',
            'invalid_role' => 'flash.project.member_invalid_role',
            'last_owner' => 'flash.project.member_last_owner',
            'cannot_manage_owner' => 'flash.project.member_cannot_manage_owner',
            'group_already_linked' => 'flash.project.group_already',
            'group_link_forbidden' => 'flash.project.group_link_forbidden',
            default => 'flash.project.member_error',
        };
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

    private function resolveProjectAuditAction(string $raw): ?UserActionType
    {
        if ('' === $raw) {
            return null;
        }

        foreach ($this->projectAuditActionTypes() as $action) {
            if ($action->value === $raw) {
                return $action;
            }
        }

        return null;
    }

    private function parseProjectAuditDate(string $raw, bool $endOfDay = false): ?DateTimeImmutable
    {
        if ('' === $raw) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        $errors = DateTimeImmutable::getLastErrors();
        if (false === $date || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            return null;
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date->setTime(0, 0, 0);
    }
}
