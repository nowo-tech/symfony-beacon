<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectMembershipManager;
use App\Shared\ProjectRole;
use InvalidArgumentException;
use RuntimeException;
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
        private readonly UserGroupRepository $userGroupRepository,
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
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        if (!$this->isCsrfTokenValid('project_member_add_'.$project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $email = $request->request->getString('email');
        $role = ProjectRole::tryFrom($request->request->getString('role')) ?? ProjectRole::Member;

        try {
            $this->membershipManager->addByEmail($project, $user, $email, $role);
            $this->addFlash('success', 'flash.project.member_added');
        } catch (RuntimeException $e) {
            if ('forbidden' === $e->getMessage()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
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
        $this->projectAccess->requireRole($project, $actor, ProjectRole::Admin);

        $target = $this->requireTargetMembership($project, $memberUser);

        if (!$this->isCsrfTokenValid('project_member_role_'.$target->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $role = ProjectRole::tryFrom($request->request->getString('role'));
        if (!$role instanceof ProjectRole) {
            $this->addFlash('error', 'flash.project.member_invalid_role');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        try {
            $this->membershipManager->changeRole($project, $actor, $target, $role);
            $this->addFlash('success', 'flash.project.member_role_updated');
        } catch (RuntimeException $e) {
            if ('forbidden' === $e->getMessage()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
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
        $this->projectAccess->requireRole($project, $actor, ProjectRole::Admin);

        $target = $this->requireTargetMembership($project, $memberUser);

        if (!$this->isCsrfTokenValid('project_member_remove_'.$target->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->membershipManager->remove($project, $actor, $target);
            $this->addFlash('success', 'flash.project.member_removed');
        } catch (RuntimeException $e) {
            if ('forbidden' === $e->getMessage()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
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
        $this->projectAccess->requireRole($project, $actor, ProjectRole::Admin);

        if (!$this->isCsrfTokenValid('project_group_add_'.$project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $groupUuid = $request->request->getString('group');
        $role = ProjectRole::tryFrom($request->request->getString('role')) ?? ProjectRole::Member;

        $group = $this->userGroupRepository->findOneBy(['uuid' => $groupUuid]);
        if (!$group instanceof UserGroup) {
            $this->addFlash('error', 'flash.project.group_not_found');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        try {
            $this->membershipManager->addGroup($project, $actor, $group, $role);
            $this->addFlash('success', 'flash.project.group_added');
        } catch (RuntimeException $e) {
            if ('forbidden' === $e->getMessage()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
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
        $this->projectAccess->requireRole($project, $actor, ProjectRole::Admin);

        if (!$this->isCsrfTokenValid('project_group_role_'.$groupAccess->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $role = ProjectRole::tryFrom($request->request->getString('role'));
        if (!$role instanceof ProjectRole) {
            $this->addFlash('error', 'flash.project.member_invalid_role');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        try {
            $this->membershipManager->changeGroupRole($project, $actor, $groupAccess, $role);
            $this->addFlash('success', 'flash.project.group_role_updated');
        } catch (RuntimeException $e) {
            if ('forbidden' === $e->getMessage()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
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
        $this->projectAccess->requireRole($project, $actor, ProjectRole::Admin);

        if (!$this->isCsrfTokenValid('project_group_remove_'.$groupAccess->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->membershipManager->removeGroup($project, $actor, $groupAccess);
            $this->addFlash('success', 'flash.project.group_removed');
        } catch (RuntimeException $e) {
            if ('forbidden' === $e->getMessage()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $this->flashKeyForCode($e->getMessage()));
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

    /**
     * Map domain exception message codes to translation keys under `flash.project.*`.
     */
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
}
