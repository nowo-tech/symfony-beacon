<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\HumanFriendlyTokenGenerator;
use App\Project\Service\ProjectHistoryClearer;
use App\Project\Service\ProjectMembershipManager;
use App\Shared\ProjectRole;
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
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly UserGroupRepository $userGroupRepository,
        private readonly ProjectMembershipManager $membershipManager,
        private readonly ProjectHistoryClearer $historyClearer,
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

        return $this->render('admin/projects/index.html.twig', [
            'projects' => $this->projectRepository->findAllOrdered('' !== $query ? $query : null),
            'q' => $query,
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
    ): Response {
        /** @var User $actor */
        $actor = $this->getUser();

        return $this->render('admin/projects/show.html.twig', [
            'project' => $project,
            'assignableRoles' => $this->membershipManager->assignableRoles($actor, $project),
            'assignableGroupRoles' => $this->membershipManager->assignableGroupRoles($actor, $project),
            'availableGroups' => $this->availableGroups($project),
            'ownerCount' => $this->countOwners($project),
        ]);
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
}
