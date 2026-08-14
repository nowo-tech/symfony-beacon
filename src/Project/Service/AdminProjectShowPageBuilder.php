<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\AdminAuditFilter;
use App\Identity\Entity\User;
use App\Identity\Form\AdminAuditTimelineFilterType;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\UserActionType;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectRole;
use App\Project\Form\ProjectDeleteType;
use App\Project\Form\ProjectGroupAddType;
use App\Project\Form\ProjectGroupRoleType;
use App\Project\Form\ProjectMemberAddType;
use App\Project\Form\ProjectMemberRoleType;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use App\Shared\Form\CsrfOnlyFormFactory;
use App\Shared\Form\GetFilterFormFactory;
use App\Shared\Health\MessengerQueueHealth;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Assembles Twig variables for the admin project detail (show) page.
 */
final readonly class AdminProjectShowPageBuilder
{
    private const int PROJECT_AUDIT_LIMIT = 100;

    public function __construct(
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
        private CsrfOnlyFormFactory $csrfOnlyFormFactory,
        private GetFilterFormFactory $getFilterFormFactory,
        private ProjectRepository $projectRepository,
        private ProjectMembershipManager $membershipManager,
        private ProjectGroupAccessManager $groupAccessManager,
        private ProjectMembershipFormSupport $membershipFormSupport,
        private ProjectMembershipRepository $membershipRepository,
        private UserGroupMembershipRepository $userGroupMembershipRepository,
        private UserGroupRepository $userGroupRepository,
        private NotificationDeliveryAttemptRepository $deliveryAttemptRepository,
        private ProjectOpsStatsService $opsStats,
        private MessengerQueueHealth $messengerQueueHealth,
        private UserActionRepository $userActionRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Project $project, User $actor, Request $request): array
    {
        $auditActions = $this->projectAuditActionTypes();
        $audit = AdminAuditFilter::fromRequest($request, $auditActions);

        $this->projectRepository->hydrateAccessGraph($project);
        $availableGroups = ProjectMembershipUiHelper::linkableGroups(
            $project,
            $this->userGroupRepository->findAllOrdered(),
        );
        $groupIds = ProjectRepository::collectGroupIds($project, $availableGroups);
        $groupMemberCounts = $this->userGroupMembershipRepository->countByGroupIds($groupIds);
        $destinations = $project->getNotificationDestinations()->toArray();
        $assignableRoles = $this->membershipManager->assignableRoles($actor, $project);
        $assignableGroupRoles = $this->groupAccessManager->assignableGroupRoles($actor, $project);
        $memberRoleChoices = ProjectMembershipUiHelper::roleChoices($assignableRoles);
        $groupRoleChoices = ProjectMembershipUiHelper::roleChoices($assignableGroupRoles);

        $removeMemberForms = [];
        $memberRoleForms = [];
        foreach ($project->getMemberships() as $membership) {
            $memberId = $membership->getId();
            $memberUser = $membership->getUser();
            if (null === $memberId || null === $memberUser?->getUuid()) {
                continue;
            }

            $removeMemberForms[$memberId] = $this->csrfOnlyFormFactory->create(
                $this->urlGenerator->generate('admin_projects_members_remove', [
                    'projectId' => $project->getUuid(),
                    'userId' => $memberUser->getUuid(),
                ]),
                'admin_project_member_remove_'.$memberId,
            )->createView();
            $memberRoleForms[$memberId] = $this->formFactory->create(ProjectMemberRoleType::class, [
                'role' => $membership->getRole()->value,
            ], [
                'action' => $this->urlGenerator->generate('admin_projects_members_role', [
                    'projectId' => $project->getUuid(),
                    'userId' => $memberUser->getUuid(),
                ]),
                'method' => 'POST',
                'csrf_token_id' => 'admin_project_member_role_'.$memberId,
                'role_choices' => $memberRoleChoices,
            ])->createView();
        }

        $removeGroupForms = [];
        $groupRoleForms = [];
        foreach ($project->getGroupAccesses() as $groupAccess) {
            $groupAccessId = $groupAccess->getId();
            if (null === $groupAccessId) {
                continue;
            }

            $removeGroupForms[$groupAccessId] = $this->csrfOnlyFormFactory->create(
                $this->urlGenerator->generate('admin_projects_groups_remove', [
                    'projectId' => $project->getUuid(),
                    'groupAccessId' => $groupAccess->getUuid(),
                ]),
                'admin_project_group_remove_'.$groupAccessId,
            )->createView();
            $groupRoleForms[$groupAccessId] = $this->formFactory->create(ProjectGroupRoleType::class, [
                'role' => $groupAccess->getRole()->value,
            ], [
                'action' => $this->urlGenerator->generate('admin_projects_groups_role', [
                    'projectId' => $project->getUuid(),
                    'groupAccessId' => $groupAccess->getUuid(),
                ]),
                'method' => 'POST',
                'csrf_token_id' => 'admin_project_group_role_'.$groupAccessId,
                'role_choices' => $groupRoleChoices,
            ])->createView();
        }

        return [
            'project' => $project,
            'assignableRoles' => $assignableRoles,
            'assignableGroupRoles' => $assignableGroupRoles,
            'availableGroups' => $availableGroups,
            'group_member_counts' => $groupMemberCounts,
            'delivery_attempts_by_destination' => $this->deliveryAttemptRepository->findRecentByDestinations($destinations),
            'ownerCount' => $this->countOwners($project),
            'opsStats' => $this->opsStats->forProject($project),
            'messengerQueue' => $this->messengerQueueHealth->asyncPending(),
            'projectAuditActions' => $auditActions,
            'projectAuditFilter' => $audit['filter'],
            'auditFilterForm' => $this->getFilterFormFactory->create(AdminAuditTimelineFilterType::class, $audit['filter'], [
                'action' => $this->urlGenerator->generate('admin_projects_show', ['id' => $project->getUuid()]),
                'action_choices' => $this->auditActionChoices($auditActions),
            ])->createView(),
            'projectAuditEntries' => $this->userActionRepository->findForProject(
                $project,
                $auditActions,
                $audit['action'],
                $audit['from'],
                $audit['to'],
                self::PROJECT_AUDIT_LIMIT,
            ),
            'ingestToggleForm' => $this->csrfOnlyFormFactory->createWithFields(
                $this->urlGenerator->generate('admin_projects_ingest_toggle', ['id' => $project->getUuid()]),
                'admin_project_ingest_'.$project->getId(),
                ['enabled' => $project->isIngestEnabled() ? '0' : '1'],
            )->createView(),
            'viewAsMemberForm' => $this->csrfOnlyFormFactory->createWithFields(
                $this->urlGenerator->generate('admin_view_as_member_enable'),
                'admin_view_as_member_enable',
                [
                    'project_uuid' => $project->getUuid(),
                    'redirect' => $this->urlGenerator->generate('project_settings', ['id' => $project->getUuid()]),
                ],
            )->createView(),
            'addMemberForm' => $this->formFactory->create(ProjectMemberAddType::class, [
                'email' => '',
                'role' => ProjectRole::Member->value,
            ], [
                'action' => $this->urlGenerator->generate('admin_projects_members_add', ['id' => $project->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'admin_project_member_add_'.$project->getId(),
                'role_choices' => $memberRoleChoices,
            ])->createView(),
            'removeMemberForms' => $removeMemberForms,
            'memberRoleForms' => $memberRoleForms,
            'addGroupForm' => $this->formFactory->create(ProjectGroupAddType::class, [
                'group' => '',
                'role' => ProjectRole::Member->value,
            ], [
                'action' => $this->urlGenerator->generate('admin_projects_groups_add', ['id' => $project->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'admin_project_group_add_'.$project->getId(),
                'group_choices' => $this->membershipFormSupport->groupChoicesForLinking(
                    $project,
                    $groupMemberCounts,
                ),
                'role_choices' => $groupRoleChoices,
            ])->createView(),
            'removeGroupForms' => $removeGroupForms,
            'groupRoleForms' => $groupRoleForms,
            'deleteProjectForm' => $this->formFactory->create(ProjectDeleteType::class, null, [
                'action' => $this->urlGenerator->generate('admin_projects_delete', ['id' => $project->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'admin_project_delete_'.$project->getId(),
                'project_id' => (int) $project->getId(),
                'confirmation_value' => $project->getName(),
                'input_id_prefix' => 'admin-project-delete-confirm-',
            ])->createView(),
        ];
    }

    private function countOwners(Project $project): int
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return 0;
        }

        return $this->membershipRepository->countOwnersByProjectIds([$projectId])[$projectId] ?? 0;
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
            UserActionType::ProjectMemberActivated,
            UserActionType::ProjectMemberDeactivated,
            UserActionType::ProjectConfigExported,
            UserActionType::ProjectConfigImported,
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

    /**
     * @param list<UserActionType> $actions
     *
     * @return array<string, string>
     */
    private function auditActionChoices(array $actions): array
    {
        $choices = [];
        foreach ($actions as $action) {
            $choices['users.activity.action.'.$action->value] = $action->value;
        }

        return $choices;
    }
}
