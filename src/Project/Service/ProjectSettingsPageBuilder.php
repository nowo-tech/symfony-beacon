<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserGroupRepository;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Notifications\Service\MemberAlertPreferenceManager;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Enum\ProjectRole;
use App\Project\Enum\ProjectSettingsSection;
use App\Project\Form\ProjectApiKeyCreateType;
use App\Project\Form\ProjectClearHistoryType;
use App\Project\Form\ProjectConfigImportType;
use App\Project\Form\ProjectDeleteType;
use App\Project\Form\ProjectGovernanceType;
use App\Project\Form\ProjectGroupAddType;
use App\Project\Form\ProjectGroupRoleType;
use App\Project\Form\ProjectMemberAddType;
use App\Project\Form\ProjectMemberRoleType;
use App\Project\Form\ProjectReadTokenCreateType;
use App\Project\Form\ProjectShareCreateType;
use App\Project\Form\ProjectTransferOwnershipType;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectReadTokenRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Ops\Messenger\MessengerQueueHealth;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Nowo\FormKitBundle\Form\Type\HiddenFieldsCsrfType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Assembles Twig variables for the project settings page.
 */
final readonly class ProjectSettingsPageBuilder
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private CsrfOnlyFormFactory $csrfOnlyFormFactory,
        private UrlGeneratorInterface $urlGenerator,
        private AuthorizationCheckerInterface $authorizationChecker,
        private MemberAlertPreferenceManager $memberAlertPreferenceManager,
        private ProjectRepository $projectRepository,
        private ProjectReadTokenRepository $readTokenRepository,
        private ProjectShareLinkRepository $shareLinkRepository,
        private HumanFriendlyTokenGenerator $tokenGenerator,
        private ProjectMembershipManager $membershipManager,
        private ProjectGroupAccessManager $groupAccessManager,
        private ProjectMembershipFormSupport $membershipFormSupport,
        private UserGroupMembershipRepository $userGroupMembershipRepository,
        private UserGroupRepository $userGroupRepository,
        private NotificationDeliveryAttemptRepository $deliveryAttemptRepository,
        private ProjectGovernanceResolver $governanceResolver,
        private MessengerQueueHealth $messengerQueueHealth,
        private ProjectMembershipRepository $membershipRepository,
        private ProjectAccessService $projectAccess,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        Project $project,
        User $user,
        ProjectAccess $access,
        Request $request,
        ProjectSettingsSection $section,
    ): array {
        $baseUrl = $request->getSchemeAndHttpHost();

        $this->projectRepository->hydrateAccessGraph($project);
        $availableGroups = $this->availableGroupsForProject($project, $user);
        $groupIds = ProjectRepository::collectGroupIds($project, $availableGroups);
        $groupMemberCounts = $this->userGroupMembershipRepository->countByGroupIds($groupIds);

        $destinations = $project->getNotificationDestinations()->toArray();
        $readTokens = $this->readTokenRepository->findByProject($project);
        $apiKeyRotateForms = [];
        $apiKeyRevokeForms = [];
        $lastApiKeyDsn = $access->canManageApiKeys()
            ? $request->getSession()->remove('_beacon_last_api_key_dsn')
            : null;
        if (!\is_string($lastApiKeyDsn) || '' === $lastApiKeyDsn) {
            $lastApiKeyDsn = null;
        }
        /** @var array<int, string> $apiKeyDsns */
        $apiKeyDsns = [];
        /** @var array<int, string> $apiKeyMaskedDsns */
        $apiKeyMaskedDsns = [];
        foreach ($project->getApiKeys() as $key) {
            $keyId = $key->getId();
            if (null === $keyId) {
                continue;
            }

            $apiKeyRotateForms[$keyId] = $this->csrfOnlyFormFactory->create('', 'project_key_rotate_'.$keyId, 'POST')->createView();
            $apiKeyRevokeForms[$keyId] = $this->csrfOnlyFormFactory->create('', 'project_key_revoke_'.$keyId, 'POST')->createView();
            if ($access->canManageApiKeys() && $key->isActive()) {
                // One-shot only: full DSN comes from the create/rotate session flash.
                if (null !== $lastApiKeyDsn && $this->dsnPublicKey($lastApiKeyDsn) === $key->getPublicKey()) {
                    $apiKeyDsns[$keyId] = $lastApiKeyDsn;
                    $apiKeyMaskedDsns[$keyId] = ProjectApiKey::maskDsn($lastApiKeyDsn);
                }
            }
        }
        // Prefer per-key copy controls; keep the flash banner only when it did not match a listed key.
        if (null !== $lastApiKeyDsn && \in_array($lastApiKeyDsn, $apiKeyDsns, true)) {
            $lastApiKeyDsn = null;
        }

        $projectRoleChoices = ProjectMembershipUiHelper::roleChoices($this->membershipManager->assignableRoles($user, $project));
        $projectGroupRoleChoices = ProjectMembershipUiHelper::roleChoices($this->groupAccessManager->assignableGroupRoles($user, $project));

        $memberRemoveForms = [];
        $memberSetActiveForms = [];
        $memberRoleForms = [];
        foreach ($project->getMemberships() as $member) {
            $memberId = $member->getId();
            $memberUser = $member->getUser();
            if (null === $memberId) {
                continue;
            }

            $memberRemoveForms[$memberId] = $this->csrfOnlyFormFactory->create('', 'project_member_remove_'.$memberId, 'POST')->createView();
            $memberSetActiveForms[$memberId] = $this->formFactory->create(HiddenFieldsCsrfType::class, [
                'active' => $member->isActive() ? '0' : '1',
            ], [
                'csrf_token_id' => 'project_member_active_'.$memberId,
                'fields' => ['active'],
            ])->createView();
            if (null !== $memberUser?->getUuid()) {
                $memberRoleForms[$memberId] = $this->formFactory->create(ProjectMemberRoleType::class, [
                    'role' => $member->getRole()->value,
                ], [
                    'action' => $this->urlGenerator->generate('project_members_role', [
                        'projectId' => $project->getUuid(),
                        'userId' => $memberUser->getUuid(),
                    ]),
                    'method' => 'POST',
                    'csrf_token_id' => 'project_member_role_'.$memberId,
                    'role_choices' => $projectRoleChoices,
                ])->createView();
            }
        }

        $groupRemoveForms = [];
        $groupRoleForms = [];
        foreach ($project->getGroupAccesses() as $groupAccess) {
            $groupAccessId = $groupAccess->getId();
            if (null === $groupAccessId) {
                continue;
            }

            $groupRemoveForms[$groupAccessId] = $this->csrfOnlyFormFactory->create('', 'project_group_remove_'.$groupAccessId, 'POST')->createView();
            $groupRoleForms[$groupAccessId] = $this->formFactory->create(ProjectGroupRoleType::class, [
                'role' => $groupAccess->getRole()->value,
            ], [
                'action' => $this->urlGenerator->generate('project_groups_role', [
                    'projectId' => $project->getUuid(),
                    'groupAccessId' => $groupAccess->getUuid(),
                ]),
                'method' => 'POST',
                'csrf_token_id' => 'project_group_role_'.$groupAccessId,
                'role_choices' => $projectGroupRoleChoices,
            ])->createView();
        }

        $readTokenRevokeForms = [];
        foreach ($readTokens as $token) {
            $tokenId = $token->getId();
            if (null === $tokenId) {
                continue;
            }

            $readTokenRevokeForms[$tokenId] = $this->csrfOnlyFormFactory->create('', 'project_read_token_revoke', 'POST')->createView();
        }

        $shareLinks = $this->shareLinkRepository->findActiveByProject($project);
        $shareRevokeForms = [];
        foreach ($shareLinks as $link) {
            $linkId = $link->getId();
            if (null === $linkId) {
                continue;
            }

            $shareRevokeForms[$linkId] = $this->csrfOnlyFormFactory->create('', 'project_share_revoke', 'POST')->createView();
        }

        $notificationResumeForms = [];
        $notificationToggleForms = [];
        $notificationTestForms = [];
        $notificationDeleteForms = [];
        foreach ($project->getNotificationDestinations() as $destination) {
            $destinationId = $destination->getId();
            if (null === $destinationId) {
                continue;
            }

            $notificationResumeForms[$destinationId] = $this->csrfOnlyFormFactory->create('', 'notif_resume_'.$destinationId, 'POST')->createView();
            $notificationToggleForms[$destinationId] = $this->csrfOnlyFormFactory->create('', 'notif_toggle_'.$destinationId, 'POST')->createView();
            $notificationTestForms[$destinationId] = $this->csrfOnlyFormFactory->create('', 'notif_test_'.$destinationId, 'POST')->createView();
            $notificationDeleteForms[$destinationId] = $this->csrfOnlyFormFactory->create('', 'notif_delete_'.$destinationId, 'POST')->createView();
        }

        $thresholdToggleForms = [];
        $thresholdDeleteForms = [];
        foreach ($project->getThresholdRules() as $rule) {
            $ruleId = $rule->getId();
            if (null === $ruleId) {
                continue;
            }

            $thresholdToggleForms[$ruleId] = $this->csrfOnlyFormFactory->create('', 'threshold_toggle_'.$ruleId, 'POST')->createView();
            $thresholdDeleteForms[$ruleId] = $this->csrfOnlyFormFactory->create('', 'threshold_delete_'.$ruleId, 'POST')->createView();
        }

        $transferOwnershipChoices = $this->membershipFormSupport->transferOwnershipChoices($project, $user);

        $memberAlertRows = $this->memberAlertPreferenceManager->projectRowsForUi($user, [$project]);
        $memberAlertRow = $memberAlertRows[0] ?? null;
        $memberAlertsInitial = [
            'enabled' => $memberAlertRow['enabled'] ?? true,
            'resetOverrides' => false,
            'events' => MemberAlertEvent::mapEventsToFormKeys($memberAlertRow['events'] ?? []),
        ];

        $governanceForm = null;
        $apiKeyCreateForm = null;
        $memberAddForm = null;
        $shareCreateForm = null;
        $readTokenCreateForm = null;
        $groupAddForm = null;
        $configImportForm = null;
        $transferOwnershipForm = null;
        $clearHistoryForm = null;
        $deleteProjectForm = null;

        if ($access->canManageSettings()) {
            $governanceForm = $this->formFactory->create(ProjectGovernanceType::class, [
                'retention_days' => $project->getRetentionDays(),
                'retention_max_events' => $project->getRetentionMaxEvents(),
                'ingest_rate_limit_per_minute' => $project->getIngestRateLimitPerMinute(),
                'event_quota_daily' => $project->getEventQuotaDaily(),
                'event_quota_monthly' => $project->getEventQuotaMonthly(),
            ], [
                'action' => $this->urlGenerator->generate('project_governance_save', ['id' => $project->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'project_governance_'.$project->getId(),
                'env_defaults' => $this->governanceResolver->envDefaults(),
            ])->createView();
            $readTokenCreateForm = $this->formFactory->create(ProjectReadTokenCreateType::class, [
                'label' => '',
            ], [
                'action' => $this->urlGenerator->generate('project_read_token_create', ['id' => $project->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'project_read_token_create',
            ])->createView();
            $configImportForm = $this->formFactory->create(ProjectConfigImportType::class, null, [
                'action' => $this->urlGenerator->generate('project_config_import', ['id' => $project->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'project_config_import_'.$project->getId(),
            ])->createView();
            $clearHistoryForm = $this->formFactory->create(ProjectClearHistoryType::class, null, [
                'csrf_token_id' => 'project_clear_'.$project->getId(),
            ])->createView();
        }

        if ($access->canManageApiKeys()) {
            $apiKeyCreateForm = $this->formFactory->create(ProjectApiKeyCreateType::class, [
                'label' => $this->tokenGenerator->generateLabel(),
            ], [
                'action' => $this->urlGenerator->generate('project_keys_create', ['id' => $project->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'project_key_create_'.$project->getId(),
            ])->createView();
        }

        if ([] !== $projectRoleChoices) {
            $memberAddForm = $this->formFactory->create(ProjectMemberAddType::class, [
                'email' => '',
                'role' => ProjectRole::Member->value,
            ], [
                'action' => $this->urlGenerator->generate('project_members_add', ['id' => $project->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'project_member_add_'.$project->getId(),
                'role_choices' => $projectRoleChoices,
            ])->createView();
        }

        if ($access->canManageShareLinks()) {
            $shareCreateForm = $this->formFactory->create(ProjectShareCreateType::class, [
                'days' => 7,
                'max_uses' => 1,
                'issue_uuid' => '',
            ], [
                'action' => $this->urlGenerator->generate('project_share_create', ['id' => $project->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'project_share_create',
            ])->createView();
        }

        if ([] !== $availableGroups && [] !== $projectGroupRoleChoices) {
            $groupAddForm = $this->formFactory->create(ProjectGroupAddType::class, [
                'group' => '',
                'role' => ProjectRole::Member->value,
            ], [
                'action' => $this->urlGenerator->generate('project_groups_add', ['id' => $project->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'project_group_add_'.$project->getId(),
                'group_choices' => $this->membershipFormSupport->groupChoicesForLinking(
                    $project,
                    $groupMemberCounts,
                    $availableGroups,
                ),
                'role_choices' => $projectGroupRoleChoices,
            ])->createView();
        }

        if ($access->isPrimaryOwner()) {
            $transferOwnershipForm = $this->formFactory->create(ProjectTransferOwnershipType::class, [
                'user' => '',
                'confirmation' => '',
            ], [
                'action' => $this->urlGenerator->generate('project_transfer_ownership', ['projectId' => $project->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'project_transfer_ownership_'.$project->getId(),
                'user_choices' => $transferOwnershipChoices,
                'project_id' => (int) $project->getId(),
                'confirmation_value' => $project->getName(),
            ])->createView();
        }

        if ($access->canDeleteProject()) {
            $deleteProjectForm = $this->formFactory->create(ProjectDeleteType::class, null, [
                'csrf_token_id' => 'project_delete_'.$project->getId(),
                'project_id' => (int) $project->getId(),
                'confirmation_value' => $project->getName(),
            ])->createView();
        }

        return [
            'project' => $project,
            'access' => $access,
            'membership' => $access, // BC alias for templates expecting .role
            'settingsSection' => $section,
            'settingsSections' => ProjectSettingsSection::visibleFor($access),
            'baseUrl' => $baseUrl,
            'labelAdjectives' => $this->tokenGenerator->adjectiveWordList(),
            'labelNouns' => $this->tokenGenerator->nounWordList(),
            'suggestedLabel' => $this->tokenGenerator->generateLabel(),
            'assignableRoles' => $this->membershipManager->assignableRoles($user, $project),
            'assignableGroupRoles' => $this->groupAccessManager->assignableGroupRoles($user, $project),
            'availableGroups' => $availableGroups,
            'group_member_counts' => $groupMemberCounts,
            'delivery_attempts_by_destination' => $this->deliveryAttemptRepository->findRecentByDestinations($destinations),
            'ownerCount' => $this->countOwners($project),
            'transferCandidates' => $transferOwnershipChoices,
            'memberAlertsInitial' => $memberAlertsInitial,
            'memberAlertsHasOverrides' => (bool) ($memberAlertRow['hasOverrides'] ?? false),
            'governanceDefaults' => $this->governanceResolver->envDefaults(),
            'eventsToday' => $this->governanceResolver->eventsReceivedToday($project),
            'effectiveQuota' => $this->governanceResolver->effectiveEventQuotaDaily($project),
            'eventsThisMonth' => $this->governanceResolver->eventsReceivedThisMonth($project),
            'effectiveMonthlyQuota' => $this->governanceResolver->effectiveEventQuotaMonthly($project),
            'governanceForm' => $governanceForm,
            'messengerQueue' => $this->messengerQueueHealth->asyncPending(),
            'apiKeyCreateForm' => $apiKeyCreateForm,
            'apiKeyRotateForms' => $apiKeyRotateForms,
            'apiKeyRevokeForms' => $apiKeyRevokeForms,
            'memberAddForm' => $memberAddForm,
            'memberRemoveForms' => $memberRemoveForms,
            'memberSetActiveForms' => $memberSetActiveForms,
            'memberRoleForms' => $memberRoleForms,
            'groupRoleForms' => $groupRoleForms,
            'groupRemoveForms' => $groupRemoveForms,
            'shareLinks' => $shareLinks,
            'shareCreateForm' => $shareCreateForm,
            'shareRevokeForms' => $shareRevokeForms,
            'lastShareUrl' => $request->getSession()->remove('_beacon_last_share_url'),
            'readTokens' => $readTokens,
            'readTokenCreateForm' => $readTokenCreateForm,
            'readTokenRevokeForms' => $readTokenRevokeForms,
            'lastReadToken' => $request->getSession()->remove('_beacon_last_read_token'),
            'lastApiKeyDsn' => $lastApiKeyDsn,
            'apiKeyDsns' => $apiKeyDsns,
            'apiKeyMaskedDsns' => $apiKeyMaskedDsns,
            'notificationResumeForms' => $notificationResumeForms,
            'notificationToggleForms' => $notificationToggleForms,
            'notificationTestForms' => $notificationTestForms,
            'notificationDeleteForms' => $notificationDeleteForms,
            'thresholdToggleForms' => $thresholdToggleForms,
            'thresholdDeleteForms' => $thresholdDeleteForms,
            'groupAddForm' => $groupAddForm,
            'configImportForm' => $configImportForm,
            'transferOwnershipForm' => $transferOwnershipForm,
            'clearHistoryForm' => $clearHistoryForm,
            'deleteProjectForm' => $deleteProjectForm,
        ];
    }

    /**
     * Groups that the actor may link: all (owner / ROLE_ADMIN) or only groups they belong to (project admin).
     *
     * @return list<UserGroup>
     */
    private function availableGroupsForProject(Project $project, User $actor): array
    {
        $canLinkAny = $this->authorizationChecker->isGranted('ROLE_ADMIN');
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

        $candidates = [];
        foreach ($this->userGroupRepository->findAllOrdered() as $group) {
            $id = $group->getId();
            if (null === $id) {
                continue;
            }
            if (!$canLinkAny && !isset($actorGroupIds[$id])) {
                continue;
            }
            $candidates[] = $group;
        }

        return ProjectMembershipUiHelper::linkableGroups($project, $candidates);
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
     * Extract the public-key userinfo segment from an Envelope DSN.
     */
    private function dsnPublicKey(string $dsn): ?string
    {
        if (1 !== preg_match('#://([^:/@]+)(?::[^@]*)?@#', $dsn, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
