<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\AdminAuditFilter;
use App\Identity\AdminIdentityAudit;
use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use App\Identity\Form\AdminAuditTimelineFilterType;
use App\Identity\Form\AdminGroupMemberAddType;
use App\Identity\Form\AdminGroupType;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Exception\ProjectAccessException;
use App\Project\Port\ProjectMembershipAdminPort;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Shared\Controller\RequiresValidFormTrait;
use App\Shared\Form\AdminSearchType;
use App\Shared\Pagination\PagePagination;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
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
    use RequiresValidFormTrait;

    public function __construct(
        private readonly UserGroupRepository $groupRepository,
        private readonly UserGroupMembershipRepository $groupMembershipRepository,
        private readonly UserRepository $userRepository,
        private readonly ProjectGroupAccessRepository $projectGroupAccessRepository,
        private readonly ProjectMembershipAdminPort $projectMembershipAdminPort,
        private readonly UserActionRecorder $actionRecorder,
        private readonly UserActionRepository $userActionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfOnlyFormFactory $csrfOnlyFormFactory,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    /** List all user groups (ordered by name; optional search). */
    #[Route('/admin/groups', name: 'admin_groups', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $request->query->getString('q');
        $total = $this->groupRepository->countAllOrdered('' !== $query ? $query : null);
        $pagination = PagePagination::fromRequest($request, $total);
        $groups = $this->groupRepository->findAllOrdered(
            '' !== $query ? $query : null,
            $pagination['per_page'],
            $pagination['offset'],
        );
        $groupIds = [];
        foreach ($groups as $group) {
            $id = $group->getId();
            if (null !== $id) {
                $groupIds[] = $id;
            }
        }

        return $this->render('admin/groups/index.html.twig', [
            'groups' => $groups,
            'q' => $query,
            'pagination' => $pagination,
            'searchForm' => $this->getFilterFormFactory->create(AdminSearchType::class, [
                'q' => $query,
            ], [
                'action' => $this->generateUrl('admin_groups'),
            ])->createView(),
            'member_counts' => $this->groupMembershipRepository->countByGroupIds($groupIds),
        ]);
    }

    /** Create a group (name, optional description; slug derived from name). */
    #[Route('/admin/groups/new', name: 'admin_groups_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $group = new UserGroup();
        $form = $this->createForm(AdminGroupType::class, $group, [
            'csrf_token_id' => 'admin_group_new',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $group->setSlug($this->uniqueSlug($group->getName()));
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
            'form' => $form,
            'group' => null,
            'is_edit' => false,
        ]);
    }

    /** Group detail: members, linked projects, and filterable audit timeline. */
    #[Route('/admin/groups/{id}', name: 'admin_groups_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        UserGroup $group,
        Request $request,
    ): Response {
        $this->groupRepository->hydrateMembers($group);
        $auditActions = AdminIdentityAudit::groupTimelineActions();
        $audit = AdminAuditFilter::fromRequest($request, $auditActions);
        $projectAccesses = $this->projectGroupAccessRepository->findByUserGroup($group);
        $removeMemberForms = [];
        foreach ($group->getMemberships() as $membership) {
            $membershipId = $membership->getId();
            $user = $membership->getUser();
            if (null === $membershipId || null === $user?->getUuid()) {
                continue;
            }

            $removeMemberForms[$membershipId] = $this->csrfOnlyFormFactory->createNamed(
                $this->generateUrl('admin_groups_members_remove', [
                    'groupId' => $group->getUuid(),
                    'userId' => $user->getUuid(),
                ]),
                'admin_group_member_remove_'.$membershipId,
            )->createView();
        }
        $removeProjectForms = [];
        foreach ($projectAccesses as $access) {
            $accessId = $access->getId();
            if (null === $accessId) {
                continue;
            }

            $removeProjectForms[$accessId] = $this->csrfOnlyFormFactory->createNamed(
                $this->generateUrl('admin_groups_projects_remove', [
                    'groupId' => $group->getUuid(),
                    'accessId' => $access->getUuid(),
                ]),
                'admin_group_project_remove_'.$accessId,
            )->createView();
        }

        return $this->render('admin/groups/show.html.twig', [
            'group' => $group,
            'projectAccesses' => $projectAccesses,
            'groupAuditActions' => $auditActions,
            'groupAuditFilter' => $audit['filter'],
            'auditFilterForm' => $this->getFilterFormFactory->create(AdminAuditTimelineFilterType::class, $audit['filter'], [
                'action' => $this->generateUrl('admin_groups_show', ['id' => $group->getUuid()]),
                'action_choices' => $this->auditActionChoices($auditActions),
            ])->createView(),
            'groupAuditEntries' => $this->userActionRepository->findForGroup(
                $group,
                $auditActions,
                $audit['action'],
                $audit['from'],
                $audit['to'],
                AdminIdentityAudit::TIMELINE_LIMIT,
            ),
            'deleteForm' => $this->csrfOnlyFormFactory->createNamed(
                $this->generateUrl('admin_groups_delete', ['id' => $group->getUuid()]),
                'admin_group_delete_'.$group->getId(),
            )->createView(),
            'addMemberForm' => $this->createForm(AdminGroupMemberAddType::class, [
                'email' => '',
            ], [
                'action' => $this->generateUrl('admin_groups_members_add', ['id' => $group->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'admin_group_member_add_'.$group->getId(),
            ])->createView(),
            'removeMemberForms' => $removeMemberForms,
            'removeProjectForms' => $removeProjectForms,
        ]);
    }

    /** Update group name/description (slug refreshed when the name slugifies differently). */
    #[Route('/admin/groups/{id}/edit', name: 'admin_groups_edit', requirements: ['id' => Requirement::UUID], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        UserGroup $group,
        Request $request,
    ): Response {
        $form = $this->createForm(AdminGroupType::class, $group, [
            'csrf_token_id' => 'admin_group_edit_'.$group->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($group->getSlug() !== $this->slugify($group->getName())) {
                $group->setSlug($this->uniqueSlug($group->getName(), $group));
            }
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

        $this->groupRepository->hydrateAudit($group);

        return $this->render('admin/groups/form.html.twig', [
            'form' => $form,
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
        $form = $this->csrfOnlyFormFactory->createNamed(
            $this->generateUrl('admin_groups_delete', ['id' => $group->getUuid()]),
            'admin_group_delete_'.$group->getId(),
        );
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

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

    /** Add an existing user to the group by email. */
    #[Route('/admin/groups/{id}/members', name: 'admin_groups_members_add', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function addMember(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        UserGroup $group,
        Request $request,
    ): RedirectResponse {
        $form = $this->createForm(AdminGroupMemberAddType::class, null, [
            'csrf_token_id' => 'admin_group_member_add_'.$group->getId(),
        ]);
        $form->handleRequest($request);
        $this->requireValidForm($form);

        /** @var array{email?: string|null} $data */
        $data = $form->getData();
        $user = $this->userRepository->findOneByEmail((string) ($data['email'] ?? ''));
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
        $form = $this->csrfOnlyFormFactory->createNamed(
            $this->generateUrl('admin_groups_members_remove', [
                'groupId' => $group->getUuid(),
                'userId' => $user->getUuid(),
            ]),
            'admin_group_member_remove_'.$membership->getId(),
        );
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

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
        $form = $this->csrfOnlyFormFactory->createNamed(
            $this->generateUrl('admin_groups_projects_remove', [
                'groupId' => $group->getUuid(),
                'accessId' => $access->getUuid(),
            ]),
            'admin_group_project_remove_'.$access->getId(),
        );
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

        $project = $access->getProject();
        if (!$project instanceof Project) {
            throw $this->createNotFoundException();
        }

        /** @var User $actor */
        $actor = $this->getUser();
        try {
            $this->projectMembershipAdminPort->unlinkGroupAccess($project, $actor, $access);
            $this->addFlash('success', 'flash.project.group_removed');
        } catch (ProjectAccessException $e) {
            $this->addFlash('error', match ($e->reasonCode) {
                ProjectAccessException::FORBIDDEN => 'flash.groups.project_unlink_forbidden',
                default => 'flash.groups.project_unlink_error',
            });
        }

        return $this->redirectToRoute('admin_groups_show', ['id' => $group->getUuid()]);
    }

    /** ASCII slug for the group name (fallback random token if empty). */
    private function slugify(string $name): string
    {
        $slug = strtolower(new AsciiSlugger()->slug($name)->toString());

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
