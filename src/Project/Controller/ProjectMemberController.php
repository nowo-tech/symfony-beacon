<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Repository\UserRepository;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectSettingsSection;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Form\ProjectGroupAddType;
use App\Project\Form\ProjectMemberAddType;
use App\Project\Form\ProjectTransferOwnershipType;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectMembershipFormSupport;
use App\Project\Service\ProjectMembershipManager;
use App\Project\Service\ProjectMembershipUiHelper;
use App\Shared\Controller\RequiresValidFormTrait;
use App\Shared\Form\CsrfOnlyType;
use App\Shared\Form\HiddenFieldsCsrfType;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * HTTP endpoints for project Settings membership and group-link forms.
 *
 * Delegates domain rules to {@see ProjectMembershipManager}; maps exception codes to flash keys.
 */
#[IsGranted('ROLE_USER')]
final class ProjectMemberController extends AbstractController
{
    use HandlesProjectAccessMutationsTrait;
    use RequiresValidFormTrait;

    public function __construct(
        private readonly ProjectAccessService $projectAccess,
        private readonly ProjectMembershipManager $membershipManager,
        private readonly ProjectMembershipFormSupport $membershipFormSupport,
        private readonly UserGroupRepository $userGroupRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /** Add a direct member by email (owner/admin/member as allowed for the actor). */
    #[Route('/projects/{id}/members', name: 'project_members_add', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::MEMBERS_MANAGE, 'project')]
    public function add(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProjectMemberAddType::class, null, [
            'csrf_token_id' => 'project_member_add_'.$project->getId(),
            'role_choices' => ProjectMembershipUiHelper::roleChoices($this->membershipManager->assignableRoles($user, $project)),
        ]);
        $form->handleRequest($request);
        $this->requireValidForm($form);

        /** @var array{email?: string|null, role?: string|null} $data */
        $data = $form->getData();
        $email = (string) ($data['email'] ?? '');
        $role = ProjectRole::tryFrom((string) ($data['role'] ?? '')) ?? ProjectRole::Member;

        $this->attemptProjectAccessMutation(
            fn (): ProjectMembership => $this->membershipManager->addByEmail($project, $user, $email, $role),
            'flash.project.member_added',
        );

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    /** Change a direct member's project role. */
    #[Route(
        '/projects/{projectId}/members/{userId}/role',
        name: 'project_members_role',
        requirements: ['projectId' => Requirement::UUID, 'userId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(ProjectPermission::MEMBERS_MANAGE, 'project')]
    public function changeRole(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['userId' => 'uuid'])]
        User $memberUser,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();

        $target = $this->requireTargetMembership($project, $memberUser);

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_member_role_'.$target->getId(),
        ]);
        $form->submit($request->request->all());
        $this->requireValidForm($form);

        $role = ProjectRole::tryFrom($request->request->getString('role'));
        if (!$role instanceof ProjectRole) {
            $this->addFlash('error', 'flash.project.member_invalid_role');

            return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
        }

        $this->attemptProjectAccessMutation(
            fn () => $this->membershipManager->changeRole($project, $actor, $target, $role),
            'flash.project.member_role_updated',
        );

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    /** Remove a direct project membership. */
    #[Route(
        '/projects/{projectId}/members/{userId}/remove',
        name: 'project_members_remove',
        requirements: ['projectId' => Requirement::UUID, 'userId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(ProjectPermission::MEMBERS_MANAGE, 'project')]
    public function remove(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['userId' => 'uuid'])]
        User $memberUser,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();

        $target = $this->requireTargetMembership($project, $memberUser);

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_member_remove_'.$target->getId(),
        ]);
        $form->submit($request->request->all());
        $this->requireValidCsrfForm($form);

        $this->attemptProjectAccessMutation(
            fn () => $this->membershipManager->remove($project, $actor, $target),
            'flash.project.member_removed',
        );

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    /** Activate or deactivate a direct membership without deleting it (089). */
    #[Route(
        '/projects/{projectId}/members/{userId}/active',
        name: 'project_members_set_active',
        requirements: ['projectId' => Requirement::UUID, 'userId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(ProjectPermission::MEMBERS_MANAGE, 'project')]
    public function setActive(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['userId' => 'uuid'])]
        User $memberUser,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();

        $target = $this->requireTargetMembership($project, $memberUser);

        $form = $this->createForm(HiddenFieldsCsrfType::class, null, [
            'csrf_token_id' => 'project_member_active_'.$target->getId(),
            'fields' => ['active'],
        ]);
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

        /** @var array{active?: string} $data */
        $data = $form->getData();
        $active = '1' === ($data['active'] ?? '');

        $this->attemptProjectAccessMutation(
            fn () => $this->membershipManager->setActive($project, $actor, $target, $active),
            $active ? 'flash.project.member_activated' : 'flash.project.member_deactivated',
        );

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    /**
     * Transfer project ownership to another direct member (owner or instance ROLE_ADMIN).
     */
    #[Route(
        '/projects/{projectId}/transfer-ownership',
        name: 'project_transfer_ownership',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function transferOwnership(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();
        $this->projectAccess->requirePrimaryOwner($project, $actor);

        $form = $this->createForm(ProjectTransferOwnershipType::class, null, [
            'csrf_token_id' => 'project_transfer_ownership_'.$project->getId(),
            'user_choices' => $this->membershipFormSupport->transferOwnershipChoices($project, $actor),
            'project_id' => (int) $project->getId(),
            'confirmation_value' => $project->getName(),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            $this->requireValidForm($form);
        }
        if (!$form->isValid()) {
            if (!$form->get('confirmation')->isValid()) {
                $this->addFlash('error', 'flash.project.transfer_confirmation_mismatch');

                return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Danger->value]);
            }

            $this->requireValidForm($form);
        }

        /** @var array{confirmation?: string|null, user?: string|null} $data */
        $data = $form->getData();

        $memberUuid = (string) ($data['user'] ?? '');
        $memberUser = $this->userRepository->findOneBy(['uuid' => $memberUuid]);
        if (!$memberUser instanceof User) {
            $this->addFlash('error', 'flash.project.member_user_not_found');

            return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Danger->value]);
        }

        $target = $this->requireTargetMembership($project, $memberUser);

        $this->attemptProjectAccessMutation(
            fn () => $this->membershipManager->transferOwnership($project, $actor, $target),
            'flash.project.ownership_transferred',
        );

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Danger->value]);
    }

    /** Link a user group to the project with admin or member role. */
    #[Route('/projects/{id}/groups', name: 'project_groups_add', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::MEMBERS_MANAGE, 'project')]
    public function addGroup(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();

        $form = $this->createForm(ProjectGroupAddType::class, null, [
            'csrf_token_id' => 'project_group_add_'.$project->getId(),
            'group_choices' => $this->membershipFormSupport->groupChoicesForLinking($project),
            'role_choices' => ProjectMembershipUiHelper::roleChoices($this->membershipManager->assignableGroupRoles($actor, $project)),
        ]);
        $form->handleRequest($request);
        $this->requireValidForm($form);

        /** @var array{group?: string|null, role?: string|null} $data */
        $data = $form->getData();
        $groupUuid = (string) ($data['group'] ?? '');
        $role = ProjectRole::tryFrom((string) ($data['role'] ?? '')) ?? ProjectRole::Member;

        $group = $this->userGroupRepository->findOneBy(['uuid' => $groupUuid]);
        if (!$group instanceof UserGroup) {
            $this->addFlash('error', 'flash.project.group_not_found');

            return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
        }

        $this->attemptProjectAccessMutation(
            fn (): ProjectGroupAccess => $this->membershipManager->addGroup($project, $actor, $group, $role),
            'flash.project.group_added',
        );

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    /** Change the role of a linked group. */
    #[Route(
        '/projects/{projectId}/groups/{groupAccessId}/role',
        name: 'project_groups_role',
        requirements: ['projectId' => Requirement::UUID, 'groupAccessId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(ProjectPermission::MEMBERS_MANAGE, 'project')]
    public function changeGroupRole(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['groupAccessId' => 'uuid'])]
        ProjectGroupAccess $groupAccess,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_group_role_'.$groupAccess->getId(),
        ]);
        $form->submit($request->request->all());
        $this->requireValidForm($form);

        $role = ProjectRole::tryFrom($request->request->getString('role'));
        if (!$role instanceof ProjectRole) {
            $this->addFlash('error', 'flash.project.member_invalid_role');

            return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
        }

        $this->attemptProjectAccessMutation(
            fn () => $this->membershipManager->changeGroupRole($project, $actor, $groupAccess, $role),
            'flash.project.group_role_updated',
        );

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    /** Unlink a group from the project. */
    #[Route(
        '/projects/{projectId}/groups/{groupAccessId}/remove',
        name: 'project_groups_remove',
        requirements: ['projectId' => Requirement::UUID, 'groupAccessId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(ProjectPermission::MEMBERS_MANAGE, 'project')]
    public function removeGroup(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['groupAccessId' => 'uuid'])]
        ProjectGroupAccess $groupAccess,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_group_remove_'.$groupAccess->getId(),
        ]);
        $form->submit($request->request->all());
        $this->requireValidCsrfForm($form);

        $this->attemptProjectAccessMutation(
            fn () => $this->membershipManager->removeGroup($project, $actor, $groupAccess),
            'flash.project.group_removed',
        );

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    /** Resolve the direct membership row for a project member or 404. */
    private function requireTargetMembership(Project $project, User $memberUser): ProjectMembership
    {
        $membership = $this->projectAccess->getDirectMembership($project, $memberUser);
        if (!$membership instanceof ProjectMembership) {
            throw $this->createNotFoundException();
        }

        return $membership;
    }
}
