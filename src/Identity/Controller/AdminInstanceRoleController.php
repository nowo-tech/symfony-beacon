<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\InstanceRole;
use App\Identity\Entity\User;
use App\Identity\Form\AdminInstanceRoleType;
use App\Identity\Repository\InstancePermissionRepository;
use App\Identity\Repository\InstanceRoleRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin CRUD for instance RBAC roles (tabbed overview, permissions, and user assignment).
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminInstanceRoleController extends AbstractController
{
    private const DETAIL_ROUTES = [
        'admin_roles_show',
        'admin_roles_users',
        'admin_roles_permissions',
    ];

    public function __construct(
        private readonly InstanceRoleRepository $roleRepository,
        private readonly InstancePermissionRepository $permissionRepository,
        private readonly UserRepository $userRepository,
        private readonly UserActionRecorder $actionRecorder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/admin/roles', name: 'admin_roles', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderIndex($request);
    }

    #[Route('/admin/roles/new', name: 'admin_roles_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return $this->redirectToRoute('admin_roles', ['new' => '1']);
        }

        $form = $this->buildCreateForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var InstanceRole $role */
            $role = $form->getData();
            if ($this->roleRepository->findOneByCode($role->getCode()) instanceof InstanceRole) {
                $this->addFlash('error', 'flash.roles.code_taken');

                return $this->redirectToRoute('admin_roles', ['new' => '1']);
            }

            /** @var User $actor */
            $actor = $this->getUser();
            $this->entityManager->persist($role);
            $this->actionRecorder->record(
                UserActionType::InstanceRoleCreated,
                $actor,
                null,
                ['role' => $role->getName(), 'code' => $role->getCode(), 'role_uuid' => $role->getUuid()],
            );
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.roles.created');

            return $this->redirectToRoute('admin_roles_show', ['id' => $role->getUuid()]);
        }

        return $this->renderIndex($request, $form, openCreate: true);
    }

    #[Route('/admin/roles/{id}', name: 'admin_roles_show', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function show(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        InstanceRole $role,
    ): Response {
        return $this->renderRoleDetail($request, $role, 'admin_roles_show');
    }

    #[Route('/admin/roles/{id}/edit', name: 'admin_roles_edit', methods: ['GET', 'POST'], requirements: ['id' => Requirement::UUID])]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        InstanceRole $role,
    ): Response {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return $this->redirectToRoute('admin_roles_show', [
                'id' => $role->getUuid(),
                'edit' => '1',
            ]);
        }

        $originalCode = $role->getCode();
        $form = $this->buildEditForm($role);
        $form->handleRequest($request);
        $returnRoute = $this->resolveReturnRoute($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$role->isSystem()) {
                $existing = $this->roleRepository->findOneByCode($role->getCode());
                if ($existing instanceof InstanceRole && $existing->getId() !== $role->getId()) {
                    $role->setCode($originalCode);
                    $this->addFlash('error', 'flash.roles.code_taken');

                    return $this->redirectToRoute($returnRoute, [
                        'id' => $role->getUuid(),
                        'edit' => '1',
                    ]);
                }
            } else {
                $role->setCode($originalCode);
            }

            /** @var User $actor */
            $actor = $this->getUser();
            $this->actionRecorder->record(
                UserActionType::InstanceRoleUpdated,
                $actor,
                null,
                ['role' => $role->getName(), 'code' => $role->getCode(), 'role_uuid' => $role->getUuid()],
            );
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.roles.updated');

            return $this->redirectToRoute($returnRoute, ['id' => $role->getUuid()]);
        }

        return $this->renderRoleDetail($request, $role, $returnRoute, $form, openEdit: true);
    }

    #[Route('/admin/roles/{id}/delete', name: 'admin_roles_delete', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        InstanceRole $role,
    ): RedirectResponse {
        if ($role->isSystem()) {
            $this->addFlash('error', 'flash.roles.system_locked');

            return $this->redirectToRoute('admin_roles_show', ['id' => $role->getUuid()]);
        }

        $this->roleRepository->hydrateDetail($role);
        if ($this->roleRepository->countAssignedUsers($role) > 0) {
            $this->addFlash('error', 'flash.roles.in_use');

            return $this->redirectToRoute('admin_roles_show', ['id' => $role->getUuid()]);
        }

        if (!$this->isCsrfTokenValid('admin_instance_role_delete_'.$role->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $actor */
        $actor = $this->getUser();
        $this->actionRecorder->record(
            UserActionType::InstanceRoleDeleted,
            $actor,
            null,
            ['role' => $role->getName(), 'code' => $role->getCode(), 'role_uuid' => $role->getUuid()],
        );
        $this->entityManager->remove($role);
        $this->entityManager->flush();
        $this->addFlash('success', 'flash.roles.deleted');

        return $this->redirectToRoute('admin_roles');
    }

    #[Route('/admin/roles/{id}/permissions', name: 'admin_roles_permissions', methods: ['GET', 'POST'], requirements: ['id' => Requirement::UUID])]
    public function permissions(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        InstanceRole $role,
    ): Response {
        $this->roleRepository->hydrateDetail($role);

        if ($request->isMethod(Request::METHOD_POST)) {
            if (!$this->isCsrfTokenValid('admin_instance_role_permissions_'.$role->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            /** @var list<string|int> $rawIds */
            $rawIds = $request->request->all('permission_ids');
            $selectedIds = [];
            foreach ($rawIds as $rawId) {
                if (is_numeric($rawId)) {
                    $selectedIds[] = (int) $rawId;
                }
            }
            $selectedIds = array_values(array_unique($selectedIds));

            $role->clearPermissions();
            if ([] !== $selectedIds) {
                /** @var list<InstancePermission> $permissions */
                $permissions = $this->permissionRepository->findBy(['id' => $selectedIds]);
                foreach ($permissions as $permission) {
                    $role->addPermission($permission);
                }
            }

            /** @var User $actor */
            $actor = $this->getUser();
            $this->actionRecorder->record(
                UserActionType::InstanceRolePermissionsUpdated,
                $actor,
                null,
                [
                    'role' => $role->getName(),
                    'code' => $role->getCode(),
                    'role_uuid' => $role->getUuid(),
                    'permission_count' => \count($selectedIds),
                ],
            );
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.roles.permissions_updated');

            return $this->redirectToRoute('admin_roles_permissions', ['id' => $role->getUuid()]);
        }

        return $this->renderRoleDetail($request, $role, 'admin_roles_permissions');
    }

    #[Route('/admin/roles/{id}/users', name: 'admin_roles_users', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function users(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        InstanceRole $role,
    ): Response {
        return $this->renderRoleDetail($request, $role, 'admin_roles_users');
    }

    #[Route('/admin/roles/{id}/users/add', name: 'admin_roles_users_add', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    public function addUser(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        InstanceRole $role,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_instance_role_user_add_'.$role->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $email = strtolower(trim($request->request->getString('email')));
        $user = $this->userRepository->findOneByEmail($email);
        if (!$user instanceof User) {
            $this->addFlash('error', 'flash.roles.user_not_found');

            return $this->redirectToRoute('admin_roles_users', ['id' => $role->getUuid()]);
        }

        if ($user->hasInstanceRole($role)) {
            $this->addFlash('error', 'flash.roles.user_already');

            return $this->redirectToRoute('admin_roles_users', ['id' => $role->getUuid()]);
        }

        $user->addInstanceRole($role);

        /** @var User $actor */
        $actor = $this->getUser();
        $this->actionRecorder->record(
            UserActionType::InstanceRoleUserAdded,
            $actor,
            $user,
            ['role' => $role->getName(), 'code' => $role->getCode(), 'role_uuid' => $role->getUuid()],
        );
        $this->entityManager->flush();
        $this->addFlash('success', 'flash.roles.user_added');

        return $this->redirectToRoute('admin_roles_users', ['id' => $role->getUuid()]);
    }

    #[Route('/admin/roles/{id}/users/{userId}/remove', name: 'admin_roles_users_remove', methods: ['POST'], requirements: ['id' => Requirement::UUID, 'userId' => Requirement::UUID])]
    public function removeUser(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        InstanceRole $role,
        #[MapEntity(mapping: ['userId' => 'uuid'])]
        User $user,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_instance_role_user_remove_'.$role->getId().'_'.$user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user->removeInstanceRole($role);

        /** @var User $actor */
        $actor = $this->getUser();
        $this->actionRecorder->record(
            UserActionType::InstanceRoleUserRemoved,
            $actor,
            $user,
            ['role' => $role->getName(), 'code' => $role->getCode(), 'role_uuid' => $role->getUuid()],
        );
        $this->entityManager->flush();
        $this->addFlash('success', 'flash.roles.user_removed');

        return $this->redirectToRoute('admin_roles_users', ['id' => $role->getUuid()]);
    }

    private function renderIndex(
        Request $request,
        ?FormInterface $invalidCreateForm = null,
        bool $openCreate = false,
    ): Response {
        $query = $request->query->getString('q');
        $roles = $this->roleRepository->findAllOrdered('' !== $query ? $query : null);
        $createForm = ($invalidCreateForm ?? $this->buildCreateForm())->createView();

        return $this->render('admin/roles/index.html.twig', [
            'roles' => $roles,
            'q' => $query,
            'createForm' => $createForm,
            'open_create' => $openCreate || $request->query->getBoolean('new'),
        ]);
    }

    private function buildCreateForm(): FormInterface
    {
        $role = new InstanceRole();
        $role->setEnabled(true);

        return $this->createForm(AdminInstanceRoleType::class, $role, [
            'action' => $this->generateUrl('admin_roles_new'),
            'method' => 'POST',
            'csrf_token_id' => 'admin_instance_role_new',
        ]);
    }

    private function buildEditForm(InstanceRole $role): FormInterface
    {
        return $this->createForm(AdminInstanceRoleType::class, $role, [
            'action' => $this->generateUrl('admin_roles_edit', ['id' => $role->getUuid()]),
            'csrf_token_id' => 'admin_instance_role_edit_'.$role->getId(),
            'code_locked' => $role->isSystem(),
        ]);
    }

    private function resolveReturnRoute(Request $request): string
    {
        $return = $request->request->getString('_return');

        return \in_array($return, self::DETAIL_ROUTES, true) ? $return : 'admin_roles_show';
    }

    private function renderRoleDetail(
        Request $request,
        InstanceRole $role,
        string $page,
        ?FormInterface $editForm = null,
        bool $openEdit = false,
    ): Response {
        $this->roleRepository->hydrateDetail($role);
        $editForm ??= $this->buildEditForm($role);
        $vars = [
            'role' => $role,
            'editForm' => $editForm->createView(),
            'open_edit' => $openEdit || $request->query->getBoolean('edit'),
            'return_route' => $page,
        ];

        return match ($page) {
            'admin_roles_users' => $this->render('admin/roles/users.html.twig', $vars),
            'admin_roles_permissions' => $this->render('admin/roles/permissions.html.twig', [
                ...$vars,
                'permissions_by_category' => $this->permissionsByCategory(),
            ]),
            default => $this->render('admin/roles/show.html.twig', $vars),
        };
    }

    /**
     * @return array<string, list<InstancePermission>>
     */
    private function permissionsByCategory(): array
    {
        $permissions = $this->permissionRepository->findAllOrdered();
        $permissionsByCategory = [];
        foreach ($permissions as $permission) {
            $permissionsByCategory[$permission->getCategory()][] = $permission;
        }

        return $permissionsByCategory;
    }
}
