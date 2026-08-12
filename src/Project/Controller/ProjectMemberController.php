<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Repository\UserRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Exception\ProjectAccessException;
use App\Project\Form\ProjectGroupAddType;
use App\Project\Form\ProjectMemberAddType;
use App\Project\Form\ProjectTransferOwnershipType;
use App\Project\Repository\ProjectRepository;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectAccessFlashKeys;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectMembershipManager;
use App\Project\Service\ProjectMembershipUiHelper;
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
    public function __construct(
        private readonly ProjectAccessService $projectAccess,
        private readonly ProjectMembershipManager $membershipManager,
        private readonly ProjectRepository $projectRepository,
        private readonly UserGroupRepository $userGroupRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /** Add a direct member by email (owner/admin/member as allowed for the actor). */
    #[Route('/projects/{id}/members', name: 'project_members_add', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function add(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requirePermission($project, $user, ProjectPermission::MEMBERS_MANAGE);

        $form = $this->createForm(ProjectMemberAddType::class, null, [
            'csrf_token_id' => 'project_member_add_'.$project->getId(),
            'role_choices' => ProjectMembershipUiHelper::roleChoices($this->membershipManager->assignableRoles($user, $project)),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid form submission.');
        }

        /** @var array{email?: string|null, role?: string|null} $data */
        $data = $form->getData();
        $email = (string) ($data['email'] ?? '');
        $role = ProjectRole::tryFrom((string) ($data['role'] ?? '')) ?? ProjectRole::Member;

        try {
            $this->membershipManager->addByEmail($project, $user, $email, $role);
            $this->addFlash('success', 'flash.project.member_added');
        } catch (ProjectAccessException $e) {
            if ($e->isForbidden()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
        }

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    /** Change a direct member's project role. */
    #[Route(
        '/projects/{projectId}/members/{userId}/role',
        name: 'project_members_role',
        requirements: ['projectId' => Requirement::UUID, 'userId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function changeRole(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['userId' => 'uuid'])]
        User $memberUser,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();
        $this->projectAccess->requirePermission($project, $actor, ProjectPermission::MEMBERS_MANAGE);

        $target = $this->requireTargetMembership($project, $memberUser);

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_member_role_'.$target->getId(),
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid form submission.');
        }

        $role = ProjectRole::tryFrom($request->request->getString('role'));
        if (!$role instanceof ProjectRole) {
            $this->addFlash('error', 'flash.project.member_invalid_role');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        try {
            $this->membershipManager->changeRole($project, $actor, $target, $role);
            $this->addFlash('success', 'flash.project.member_role_updated');
        } catch (ProjectAccessException $e) {
            if ($e->isForbidden()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
        }

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    /** Remove a direct project membership. */
    #[Route(
        '/projects/{projectId}/members/{userId}/remove',
        name: 'project_members_remove',
        requirements: ['projectId' => Requirement::UUID, 'userId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function remove(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['userId' => 'uuid'])]
        User $memberUser,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();
        $this->projectAccess->requirePermission($project, $actor, ProjectPermission::MEMBERS_MANAGE);

        $target = $this->requireTargetMembership($project, $memberUser);

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_member_remove_'.$target->getId(),
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->membershipManager->remove($project, $actor, $target);
            $this->addFlash('success', 'flash.project.member_removed');
        } catch (ProjectAccessException $e) {
            if ($e->isForbidden()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
        }

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    /** Activate or deactivate a direct membership without deleting it (089). */
    #[Route(
        '/projects/{projectId}/members/{userId}/active',
        name: 'project_members_set_active',
        requirements: ['projectId' => Requirement::UUID, 'userId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function setActive(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['userId' => 'uuid'])]
        User $memberUser,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();
        $this->projectAccess->requirePermission($project, $actor, ProjectPermission::MEMBERS_MANAGE);

        $target = $this->requireTargetMembership($project, $memberUser);

        $form = $this->createForm(HiddenFieldsCsrfType::class, null, [
            'csrf_token_id' => 'project_member_active_'.$target->getId(),
            'fields' => ['active'],
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var array{active?: string} $data */
        $data = $form->getData();
        $active = '1' === ($data['active'] ?? '');

        try {
            $this->membershipManager->setActive($project, $actor, $target, $active);
            $this->addFlash('success', $active ? 'flash.project.member_activated' : 'flash.project.member_deactivated');
        } catch (ProjectAccessException $e) {
            if ($e->isForbidden()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
        }

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
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
            'user_choices' => $this->buildTransferOwnershipChoices($project, $actor),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid form submission.');
        }

        /** @var array{confirmation?: string|null, user?: string|null} $data */
        $data = $form->getData();
        $confirmation = (string) ($data['confirmation'] ?? '');
        if ($confirmation !== $project->getName()) {
            $this->addFlash('error', 'flash.project.transfer_confirmation_mismatch');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        $memberUuid = (string) ($data['user'] ?? '');
        $memberUser = $this->userRepository->findOneBy(['uuid' => $memberUuid]);
        if (!$memberUser instanceof User) {
            $this->addFlash('error', 'flash.project.member_user_not_found');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        $target = $this->requireTargetMembership($project, $memberUser);

        try {
            $this->membershipManager->transferOwnership($project, $actor, $target);
            $this->addFlash('success', 'flash.project.ownership_transferred');
        } catch (ProjectAccessException $e) {
            if ($e->isForbidden()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
        }

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    /** Link a user group to the project with admin or member role. */
    #[Route('/projects/{id}/groups', name: 'project_groups_add', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function addGroup(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();
        $this->projectAccess->requirePermission($project, $actor, ProjectPermission::MEMBERS_MANAGE);

        $form = $this->createForm(ProjectGroupAddType::class, null, [
            'csrf_token_id' => 'project_group_add_'.$project->getId(),
            'group_choices' => $this->groupChoicesForForm($project),
            'role_choices' => ProjectMembershipUiHelper::roleChoices($this->membershipManager->assignableGroupRoles($actor, $project)),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid form submission.');
        }

        /** @var array{group?: string|null, role?: string|null} $data */
        $data = $form->getData();
        $groupUuid = (string) ($data['group'] ?? '');
        $role = ProjectRole::tryFrom((string) ($data['role'] ?? '')) ?? ProjectRole::Member;

        $group = $this->userGroupRepository->findOneBy(['uuid' => $groupUuid]);
        if (!$group instanceof UserGroup) {
            $this->addFlash('error', 'flash.project.group_not_found');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        try {
            $this->membershipManager->addGroup($project, $actor, $group, $role);
            $this->addFlash('success', 'flash.project.group_added');
        } catch (ProjectAccessException $e) {
            if ($e->isForbidden()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
        }

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    /** Change the role of a linked group. */
    #[Route(
        '/projects/{projectId}/groups/{groupAccessId}/role',
        name: 'project_groups_role',
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
        /** @var User $actor */
        $actor = $this->getUser();
        $this->projectAccess->requirePermission($project, $actor, ProjectPermission::MEMBERS_MANAGE);

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_group_role_'.$groupAccess->getId(),
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid form submission.');
        }

        $role = ProjectRole::tryFrom($request->request->getString('role'));
        if (!$role instanceof ProjectRole) {
            $this->addFlash('error', 'flash.project.member_invalid_role');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        try {
            $this->membershipManager->changeGroupRole($project, $actor, $groupAccess, $role);
            $this->addFlash('success', 'flash.project.group_role_updated');
        } catch (ProjectAccessException $e) {
            if ($e->isForbidden()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
        }

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    /** Unlink a group from the project. */
    #[Route(
        '/projects/{projectId}/groups/{groupAccessId}/remove',
        name: 'project_groups_remove',
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
        /** @var User $actor */
        $actor = $this->getUser();
        $this->projectAccess->requirePermission($project, $actor, ProjectPermission::MEMBERS_MANAGE);

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_group_remove_'.$groupAccess->getId(),
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->membershipManager->removeGroup($project, $actor, $groupAccess);
            $this->addFlash('success', 'flash.project.group_removed');
        } catch (ProjectAccessException $e) {
            if ($e->isForbidden()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
        }

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
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

    /** @return array<string, string> */
    private function groupChoicesForForm(Project $project): array
    {
        $this->projectRepository->hydrateAccessGraph($project);

        return ProjectMembershipUiHelper::groupChoicesForLinking(
            $project,
            $this->userGroupRepository->findAllOrdered(),
        );
    }

    /** @return array<string, string> */
    private function buildTransferOwnershipChoices(Project $project, User $actor): array
    {
        $this->projectRepository->hydrateAccessGraph($project);

        return ProjectMembershipUiHelper::transferOwnershipChoices($project, $actor);
    }
}
