<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Form\ProjectGroupAddType;
use App\Project\Form\ProjectMemberAddType;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Exception\ProjectAccessException;
use App\Project\Service\ProjectAccessFlashKeys;
use App\Project\Service\ProjectMembershipManager;
use App\Project\Service\ProjectMembershipUiHelper;
use App\Shared\Form\CsrfOnlyType;
use App\Shared\Form\CsrfOnlyFormFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Instance-admin project membership and group-access mutations.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminProjectAccessController extends AbstractController
{
    public function __construct(
        private readonly ProjectMembershipManager $membershipManager,
        private readonly ProjectMembershipRepository $membershipRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly UserGroupRepository $userGroupRepository,
        private readonly CsrfOnlyFormFactory $csrfOnlyFormFactory,
    ) {
    }

    /** Add a direct member by email. */
    #[Route('/admin/projects/{id}/members', name: 'admin_projects_members_add', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function addMember(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $this->getUser();
        $form = $this->createForm(ProjectMemberAddType::class, null, [
            'csrf_token_id' => 'admin_project_member_add_'.$project->getId(),
            'role_choices' => ProjectMembershipUiHelper::roleChoices($this->membershipManager->assignableRoles($actor, $project)),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid form submission.');
        }

        /** @var array{email?: string|null, role?: string|null} $data */
        $data = $form->getData();
        $role = ProjectRole::tryFrom((string) ($data['role'] ?? '')) ?? ProjectRole::Member;

        try {
            $this->membershipManager->addByEmail($project, $actor, (string) ($data['email'] ?? ''), $role);
            $this->addFlash('success', 'flash.project.member_added');
        } catch (ProjectAccessException $e) {
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
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
        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'admin_project_member_role_'.$target->getId(),
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid form submission.');
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
        } catch (ProjectAccessException $e) {
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
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
        $form = $this->csrfOnlyFormFactory->create(
            $this->generateUrl('admin_projects_members_remove', [
                'projectId' => $project->getUuid(),
                'userId' => $memberUser->getUuid(),
            ]),
            'admin_project_member_remove_'.$target->getId(),
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $actor */
        $actor = $this->getUser();
        try {
            $this->membershipManager->remove($project, $actor, $target);
            $this->addFlash('success', 'flash.project.member_removed');
        } catch (ProjectAccessException $e) {
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
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
        /** @var User $actor */
        $actor = $this->getUser();
        $form = $this->createForm(ProjectGroupAddType::class, null, [
            'csrf_token_id' => 'admin_project_group_add_'.$project->getId(),
            'group_choices' => $this->groupChoicesForForm($project),
            'role_choices' => ProjectMembershipUiHelper::roleChoices($this->membershipManager->assignableGroupRoles($actor, $project)),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid form submission.');
        }

        /** @var array{group?: string|null, role?: string|null} $data */
        $data = $form->getData();
        $group = $this->userGroupRepository->findOneBy(['uuid' => (string) ($data['group'] ?? '')]);
        if (!$group instanceof UserGroup) {
            $this->addFlash('error', 'flash.project.group_not_found');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        $role = ProjectRole::tryFrom((string) ($data['role'] ?? '')) ?? ProjectRole::Member;
        try {
            $this->membershipManager->addGroup($project, $actor, $group, $role);
            $this->addFlash('success', 'flash.project.group_added');
        } catch (ProjectAccessException $e) {
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
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
        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'admin_project_group_role_'.$groupAccess->getId(),
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid form submission.');
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
        } catch (ProjectAccessException $e) {
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
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
        $form = $this->csrfOnlyFormFactory->create(
            $this->generateUrl('admin_projects_groups_remove', [
                'projectId' => $project->getUuid(),
                'groupAccessId' => $groupAccess->getUuid(),
            ]),
            'admin_project_group_remove_'.$groupAccess->getId(),
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $actor */
        $actor = $this->getUser();
        try {
            $this->membershipManager->removeGroup($project, $actor, $groupAccess);
            $this->addFlash('success', 'flash.project.group_removed');
        } catch (ProjectAccessException $e) {
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
        }

        return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
    }

    private function requireDirectMembership(Project $project, User $user): ProjectMembership
    {
        $membership = $this->membershipRepository->findOneByProjectAndUser($project, $user);
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
}
