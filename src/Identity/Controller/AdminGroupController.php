<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Service\ProjectMembershipManager;
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
 * Admin CRUD for user groups and their members.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminGroupController extends AbstractController
{
    public function __construct(
        private readonly UserGroupRepository $groupRepository,
        private readonly UserGroupMembershipRepository $groupMembershipRepository,
        private readonly UserRepository $userRepository,
        private readonly ProjectGroupAccessRepository $projectGroupAccessRepository,
        private readonly ProjectMembershipManager $projectMembershipManager,
        private readonly UserActionRecorder $actionRecorder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** List all user groups (ordered by name). */
    #[Route('/admin/groups', name: 'admin_groups', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/groups/index.html.twig', [
            'groups' => $this->groupRepository->findAllOrdered(),
        ]);
    }

    /** Create a group (name, optional description; slug derived from name). */
    #[Route('/admin/groups/new', name: 'admin_groups_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_group_new', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $name = trim($request->request->getString('name'));
            if ('' === $name) {
                $this->addFlash('error', 'flash.groups.name_required');

                return $this->redirectToRoute('admin_groups_new');
            }

            $group = new UserGroup();
            $group->setName($name);
            $group->setSlug($this->uniqueSlug($name));
            $group->setDescription($request->request->getString('description') ?: null);
            $this->entityManager->persist($group);
            /** @var User $actor */
            $actor = $this->getUser();
            $this->actionRecorder->record(
                UserActionType::GroupCreated,
                $actor,
                null,
                ['group' => $group->getName(), 'group_uuid' => $group->getUuid()],
            );
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.groups.created');

            return $this->redirectToRoute('admin_groups_show', ['id' => $group->getUuid()]);
        }

        return $this->render('admin/groups/form.html.twig', [
            'group' => null,
            'is_edit' => false,
        ]);
    }

    /** Group detail: members and linked projects. */
    #[Route('/admin/groups/{id}', name: 'admin_groups_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        UserGroup $group,
    ): Response {
        return $this->render('admin/groups/show.html.twig', [
            'group' => $group,
            'projectAccesses' => $this->projectGroupAccessRepository->findByUserGroup($group),
        ]);
    }

    /** Update group name/description (slug refreshed when the name slugifies differently). */
    #[Route('/admin/groups/{id}/edit', name: 'admin_groups_edit', requirements: ['id' => Requirement::UUID], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        UserGroup $group,
        Request $request,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_group_edit_'.$group->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $name = trim($request->request->getString('name'));
            if ('' === $name) {
                $this->addFlash('error', 'flash.groups.name_required');

                return $this->redirectToRoute('admin_groups_edit', ['id' => $group->getUuid()]);
            }

            $group->setName($name);
            if ($group->getSlug() !== $this->slugify($name)) {
                $group->setSlug($this->uniqueSlug($name, $group));
            }
            $group->setDescription($request->request->getString('description') ?: null);
            /** @var User $actor */
            $actor = $this->getUser();
            $this->actionRecorder->record(
                UserActionType::GroupUpdated,
                $actor,
                null,
                ['group' => $group->getName(), 'group_uuid' => $group->getUuid()],
            );
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.groups.updated');

            return $this->redirectToRoute('admin_groups_show', ['id' => $group->getUuid()]);
        }

        return $this->render('admin/groups/form.html.twig', [
            'group' => $group,
            'is_edit' => true,
        ]);
    }

    /** Delete a group (cascades memberships and project group links). */
    #[Route('/admin/groups/{id}/delete', name: 'admin_groups_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        UserGroup $group,
        Request $request,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_group_delete_'.$group->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $name = $group->getName();
        $uuid = $group->getUuid();
        /** @var User $actor */
        $actor = $this->getUser();
        $this->actionRecorder->record(
            UserActionType::GroupDeleted,
            $actor,
            null,
            ['group' => $name, 'group_uuid' => $uuid],
        );
        $this->entityManager->remove($group);
        $this->entityManager->flush();
        $this->addFlash('success', 'flash.groups.deleted');

        return $this->redirectToRoute('admin_groups');
    }

    /** Add an existing user to the group by email. */
    #[Route('/admin/groups/{id}/members', name: 'admin_groups_members_add', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function addMember(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        UserGroup $group,
        Request $request,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_group_member_add_'.$group->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->userRepository->findOneByEmail($request->request->getString('email'));
        if (!$user instanceof User) {
            $this->addFlash('error', 'flash.groups.user_not_found');

            return $this->redirectToRoute('admin_groups_show', ['id' => $group->getUuid()]);
        }
        if ($this->groupMembershipRepository->findOneByGroupAndUser($group, $user) instanceof UserGroupMembership) {
            $this->addFlash('error', 'flash.groups.already_member');

            return $this->redirectToRoute('admin_groups_show', ['id' => $group->getUuid()]);
        }

        $membership = new UserGroupMembership();
        $membership->setUser($user);
        $group->addMembership($membership);
        /** @var User $actor */
        $actor = $this->getUser();
        $this->actionRecorder->record(
            UserActionType::GroupMemberAdded,
            $actor,
            $user,
            ['group' => $group->getName(), 'group_uuid' => $group->getUuid()],
        );
        $this->entityManager->flush();
        $this->addFlash('success', 'flash.groups.member_added');

        return $this->redirectToRoute('admin_groups_show', ['id' => $group->getUuid()]);
    }

    /** Remove a user from the group (both resolved by public UUID). */
    #[Route(
        '/admin/groups/{groupId}/members/{userId}/remove',
        name: 'admin_groups_members_remove',
        requirements: ['groupId' => Requirement::UUID, 'userId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function removeMember(
        #[MapEntity(mapping: ['groupId' => 'uuid'])]
        UserGroup $group,
        #[MapEntity(mapping: ['userId' => 'uuid'])]
        User $user,
        Request $request,
    ): RedirectResponse {
        $membership = $this->groupMembershipRepository->findOneByGroupAndUser($group, $user);
        if (!$membership instanceof UserGroupMembership) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('admin_group_member_remove_'.$membership->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $group->removeMembership($membership);
        $this->entityManager->remove($membership);
        /** @var User $actor */
        $actor = $this->getUser();
        $this->actionRecorder->record(
            UserActionType::GroupMemberRemoved,
            $actor,
            $user,
            ['group' => $group->getName(), 'group_uuid' => $group->getUuid()],
        );
        $this->entityManager->flush();
        $this->addFlash('success', 'flash.groups.member_removed');

        return $this->redirectToRoute('admin_groups_show', ['id' => $group->getUuid()]);
    }

    /** Unlink a project from this group (instance admin). */
    #[Route(
        '/admin/groups/{groupId}/projects/{accessId}/remove',
        name: 'admin_groups_projects_remove',
        requirements: ['groupId' => Requirement::UUID, 'accessId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function removeProject(
        #[MapEntity(mapping: ['groupId' => 'uuid'])]
        UserGroup $group,
        #[MapEntity(mapping: ['accessId' => 'uuid'])]
        ProjectGroupAccess $access,
        Request $request,
    ): RedirectResponse {
        if ($access->getUserGroup()?->getId() !== $group->getId()) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('admin_group_project_remove_'.$access->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $project = $access->getProject();
        if (null === $project) {
            throw $this->createNotFoundException();
        }

        /** @var User $actor */
        $actor = $this->getUser();
        try {
            $this->projectMembershipManager->removeGroup($project, $actor, $access);
            $this->addFlash('success', 'flash.project.group_removed');
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->addFlash('error', match ($e->getMessage()) {
                'forbidden' => 'flash.groups.project_unlink_forbidden',
                default => 'flash.groups.project_unlink_error',
            });
        }

        return $this->redirectToRoute('admin_groups_show', ['id' => $group->getUuid()]);
    }

    /** ASCII slug for the group name (fallback random token if empty). */
    private function slugify(string $name): string
    {
        $slug = strtolower((new AsciiSlugger())->slug($name)->toString());

        return '' !== $slug ? $slug : 'group-'.bin2hex(random_bytes(3));
    }

    /**
     * Allocate a unique slug, appending -2, -3, … on collision.
     *
     * @param UserGroup|null $except Group allowed to keep its current slug when renaming
     */
    private function uniqueSlug(string $name, ?UserGroup $except = null): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $i = 2;
        while (true) {
            $existing = $this->groupRepository->findOneBySlug($slug);
            if (!$existing instanceof UserGroup || ($except instanceof UserGroup && $existing->getId() === $except->getId())) {
                return $slug;
            }
            $slug = $base.'-'.$i;
            ++$i;
        }
    }
}
