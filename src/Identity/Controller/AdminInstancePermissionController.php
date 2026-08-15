<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\User;
use App\Identity\Form\AdminInstancePermissionType;
use App\Identity\Repository\InstancePermissionRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Shared\Controller\RequiresValidFormTrait;
use App\Shared\Form\AdminSearchType;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin catalog for instance permission keys used by RBAC roles.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminInstancePermissionController extends AbstractController
{
    use RequiresValidFormTrait;

    public function __construct(
        private readonly InstancePermissionRepository $permissionRepository,
        private readonly UserActionRecorder $actionRecorder,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly CsrfOnlyFormFactory $csrfOnlyFormFactory,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/admin/permissions', name: 'admin_permissions', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderIndex($request);
    }

    #[Route('/admin/permissions/new', name: 'admin_permissions_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return $this->redirectToRoute('admin_permissions', ['new' => '1']);
        }

        $form = $this->buildCreateForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var InstancePermission $permission */
            $permission = $form->getData();
            if ($this->permissionRepository->findOneByKey($permission->getKey()) instanceof InstancePermission) {
                $this->addFlash('error', 'flash.permissions.key_taken');

                return $this->redirectToRoute('admin_permissions', ['new' => '1']);
            }

            $permission->setSystem(false);
            /** @var User $actor */
            $actor = $this->getUser();
            $this->entityManager->persist($permission);
            $this->actionRecorder->record(
                UserActionType::InstancePermissionCreated,
                $actor,
                null,
                [
                    'permission' => $permission->getName(),
                    'key' => $permission->getKey(),
                    'permission_uuid' => $permission->getUuid(),
                ],
            );
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.permissions.created');

            return $this->redirectToRoute('admin_permissions');
        }

        return $this->renderIndex($request, invalidCreateForm: $form, openCreate: true);
    }

    #[Route('/admin/permissions/{id}/edit', name: 'admin_permissions_edit', requirements: ['id' => Requirement::UUID], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        InstancePermission $permission,
    ): Response {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return $this->redirectToRoute('admin_permissions', ['edit' => $permission->getUuid()]);
        }

        $originalKey = $permission->getKey();
        $form = $this->buildEditForm($permission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$permission->isSystem()) {
                $existing = $this->permissionRepository->findOneByKey($permission->getKey());
                if ($existing instanceof InstancePermission && $existing->getId() !== $permission->getId()) {
                    $permission->setKey($originalKey);
                    $this->addFlash('error', 'flash.permissions.key_taken');

                    return $this->redirectToRoute('admin_permissions', ['edit' => $permission->getUuid()]);
                }
            } else {
                $permission->setKey($originalKey);
            }

            /** @var User $actor */
            $actor = $this->getUser();
            $this->actionRecorder->record(
                UserActionType::InstancePermissionUpdated,
                $actor,
                null,
                [
                    'permission' => $permission->getName(),
                    'key' => $permission->getKey(),
                    'permission_uuid' => $permission->getUuid(),
                ],
            );
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.permissions.updated');

            return $this->redirectToRoute('admin_permissions');
        }

        return $this->renderIndex($request, $permission, $form);
    }

    #[Route('/admin/permissions/{id}/delete', name: 'admin_permissions_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        InstancePermission $permission,
    ): RedirectResponse {
        if ($permission->isSystem()) {
            $this->addFlash('error', 'flash.permissions.system_locked');

            return $this->redirectToRoute('admin_permissions');
        }

        $form = $this->csrfOnlyFormFactory->createNamed(
            $this->generateUrl('admin_permissions_delete', ['id' => $permission->getUuid()]),
            'admin_instance_permission_delete_'.$permission->getId(),
        );
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

        /** @var User $actor */
        $actor = $this->getUser();
        $this->actionRecorder->record(
            UserActionType::InstancePermissionDeleted,
            $actor,
            null,
            [
                'permission' => $permission->getName(),
                'key' => $permission->getKey(),
                'permission_uuid' => $permission->getUuid(),
            ],
        );
        $this->entityManager->remove($permission);
        $this->entityManager->flush();
        $this->addFlash('success', 'flash.permissions.deleted');

        return $this->redirectToRoute('admin_permissions');
    }

    /**
     * @param FormInterface<mixed>|null $invalidEditForm
     * @param FormInterface<mixed>|null $invalidCreateForm
     */
    private function renderIndex(
        Request $request,
        ?InstancePermission $openPermission = null,
        ?FormInterface $invalidEditForm = null,
        ?FormInterface $invalidCreateForm = null,
        bool $openCreate = false,
    ): Response {
        $query = $request->query->getString('q');
        $permissions = $this->permissionRepository->findAllOrdered('' !== $query ? $query : null);
        $byCategory = [];
        foreach ($permissions as $permission) {
            $byCategory[$permission->getCategory()][] = $permission;
        }

        $openEditUuid = $openPermission?->getUuid() ?? $request->query->getString('edit');
        $openCreate = $openCreate || $request->query->getBoolean('new');
        /** @var array<int, FormView> $editForms */
        $editForms = [];
        /** @var array<int, FormView> $deleteForms */
        $deleteForms = [];
        foreach ($permissions as $permission) {
            $permissionId = $permission->getId();
            if (null === $permissionId) {
                continue;
            }
            if (
                $invalidEditForm instanceof FormInterface
                && $openPermission instanceof InstancePermission
                && $permission->getId() === $openPermission->getId()
            ) {
                $editForms[$permissionId] = $invalidEditForm->createView();
                $deleteForms[$permissionId] = $this->csrfOnlyFormFactory->createNamed(
                    $this->generateUrl('admin_permissions_delete', ['id' => $permission->getUuid()]),
                    'admin_instance_permission_delete_'.$permissionId,
                )->createView();
                continue;
            }
            $editForms[$permissionId] = $this->buildEditForm($permission)->createView();
            $deleteForms[$permissionId] = $this->csrfOnlyFormFactory->createNamed(
                $this->generateUrl('admin_permissions_delete', ['id' => $permission->getUuid()]),
                'admin_instance_permission_delete_'.$permissionId,
            )->createView();
        }

        $createForm = ($invalidCreateForm ?? $this->buildCreateForm())->createView();

        return $this->render('admin/permissions/index.html.twig', [
            'permissions' => $permissions,
            'permissions_by_category' => $byCategory,
            'q' => $query,
            'searchForm' => $this->getFilterFormFactory->create(AdminSearchType::class, [
                'q' => $query,
            ], [
                'action' => $this->generateUrl('admin_permissions'),
            ])->createView(),
            'editForms' => $editForms,
            'deleteForms' => $deleteForms,
            'createForm' => $createForm,
            'open_edit_uuid' => '' !== $openEditUuid ? $openEditUuid : null,
            'open_create' => $openCreate,
        ]);
    }

    /** @return FormInterface<mixed> */
    private function buildCreateForm(): FormInterface
    {
        $permission = new InstancePermission();
        $permission->setCategory('custom');

        return $this->formFactory->createNamed(
            'admin_instance_permission_new',
            AdminInstancePermissionType::class,
            $permission,
            [
                'action' => $this->generateUrl('admin_permissions_new'),
                'method' => 'POST',
                'csrf_token_id' => 'admin_instance_permission_new',
                'key_locked' => false,
                ...$this->permissionFormLocaleOptions(),
            ],
        );
    }

    /** @return FormInterface<mixed> */
    private function buildEditForm(InstancePermission $permission): FormInterface
    {
        return $this->formFactory->createNamed(
            'admin_instance_permission_'.$permission->getId(),
            AdminInstancePermissionType::class,
            $permission,
            [
                'action' => $this->generateUrl('admin_permissions_edit', ['id' => $permission->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'admin_instance_permission_edit_'.$permission->getId(),
                'key_locked' => $permission->isSystem(),
                ...$this->permissionFormLocaleOptions(),
            ],
        );
    }

    /**
     * Locale options for permission labels: required/default = `%default_locale%` (`DEFAULT_LOCALE`).
     *
     * @return array{enabled_locales: list<string>, default_locale: string}
     */
    private function permissionFormLocaleOptions(): array
    {
        /** @var list<string> $locales */
        $locales = array_values($this->getParameter('kernel.enabled_locales'));
        $defaultLocale = strtolower(trim((string) $this->getParameter('default_locale')));
        $ordered = [$defaultLocale];
        foreach ($locales as $locale) {
            $locale = strtolower(trim($locale));
            if ('' === $locale || $locale === $defaultLocale) {
                continue;
            }
            $ordered[] = $locale;
        }

        return [
            'enabled_locales' => $ordered,
            'default_locale' => $defaultLocale,
        ];
    }
}
