<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\AdminAuditFilter;
use App\Identity\AdminIdentityAudit;
use App\Identity\Entity\User;
use App\Identity\Exception\AccountAnonymizeException;
use App\Identity\Form\AdminAuditTimelineFilterType;
use App\Identity\Form\AdminUserRoleConfirmType;
use App\Identity\Form\AdminUserType;
use App\Identity\Form\TypeToConfirmType;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\AccountAnonymizer;
use App\Identity\Service\AccountDataExporter;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Exception\ProjectAccessException;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Service\ProjectMembershipManager;
use App\Shared\Form\AdminSearchType;
use App\Shared\Form\CsrfOnlyFormFactory;
use App\Shared\Form\GetFilterFormFactory;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lists users and recent instance activity for ROLE_ADMIN operators.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserActionRepository $userActionRepository,
        private readonly ProjectMembershipRepository $projectMembershipRepository,
        private readonly ProjectMembershipManager $projectMembershipManager,
        private readonly UserActionRecorder $actionRecorder,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AccountDataExporter $accountDataExporter,
        private readonly AccountAnonymizer $accountAnonymizer,
        private readonly CsrfOnlyFormFactory $csrfOnlyFormFactory,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    /** User directory with enable/role controls and a recent-activity strip. */
    #[Route('/admin/users', name: 'admin_users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderIndex($request);
    }

    /**
     * Create a Beacon account (email, display name, password, instance role).
     * GET opens the create modal on the directory; POST processes the form.
     */
    #[Route('/admin/users/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return $this->redirectToRoute('admin_users', ['new' => '1']);
        }

        $form = $this->buildCreateForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string, displayName: string, password: string, role: string, enabled: bool} $data */
            $data = $form->getData();
            $email = strtolower(trim($data['email']));
            $displayName = trim($data['displayName']);
            $password = $data['password'];
            $role = $data['role'];
            $enabled = (bool) $data['enabled'];

            if ($this->userRepository->findOneByEmail($email) instanceof User) {
                $this->addFlash('error', 'flash.users.email_taken');

                return $this->redirectToRoute('admin_users', ['new' => '1']);
            }

            /** @var User $actor */
            $actor = $this->getUser();

            $user = new User();
            $user->setEmail($email);
            $user->setDisplayName($displayName);
            $user->setRoles('admin' === $role ? ['ROLE_ADMIN'] : []);
            $user->setEnabled($enabled);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setPasswordChangedAt(new DateTime());

            $this->entityManager->persist($user);
            $this->actionRecorder->record(
                UserActionType::UserCreated,
                $actor,
                $user,
                [
                    'email' => $user->getEmail(),
                    'role' => $role,
                    'enabled' => $enabled ? 1 : 0,
                ],
            );
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.users.created');

            return $this->redirectToRoute('admin_users');
        }

        return $this->renderIndex($request, $form, openCreate: true);
    }

    /**
     * Per-user activity timeline (actions where the user is subject or actor).
     *
     * @param User $user Resolved by public UUID path segment
     */
    #[Route('/admin/users/{id}/activity', name: 'admin_users_activity', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function activity(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        User $user,
        Request $request,
    ): Response {
        $auditActions = AdminIdentityAudit::userTimelineActions();
        $audit = AdminAuditFilter::fromRequest($request, $auditActions);
        $memberships = $this->projectMembershipRepository->findByUser($user);
        $removeProjectForms = [];
        foreach ($memberships as $membership) {
            $membershipId = $membership->getId();
            $project = $membership->getProject();
            if (null === $membershipId || null === $project?->getUuid()) {
                continue;
            }

            $removeProjectForms[$membershipId] = $this->csrfOnlyFormFactory->create(
                $this->generateUrl('admin_users_projects_remove', [
                    'userId' => $user->getUuid(),
                    'projectId' => $project->getUuid(),
                ]),
                'admin_user_project_remove_'.$membershipId,
            )->createView();
        }

        return $this->render('admin/users/activity.html.twig', [
            'user' => $user,
            'userAuditActions' => $auditActions,
            'userAuditFilter' => $audit['filter'],
            'auditFilterForm' => $this->getFilterFormFactory->create(AdminAuditTimelineFilterType::class, $audit['filter'], [
                'action' => $this->generateUrl('admin_users_activity', ['id' => $user->getUuid()]),
                'action_choices' => $this->auditActionChoices($auditActions),
            ])->createView(),
            'actions' => $this->userActionRepository->findForUser(
                $user,
                $auditActions,
                $audit['action'],
                $audit['from'],
                $audit['to'],
                AdminIdentityAudit::TIMELINE_LIMIT,
            ),
            'memberships' => $memberships,
            'removeProjectForms' => $removeProjectForms,
        ]);
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

    /** Remove a direct project membership (instance admin). */
    #[Route(
        '/admin/users/{userId}/projects/{projectId}/remove',
        name: 'admin_users_projects_remove',
        requirements: ['userId' => Requirement::UUID, 'projectId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function removeProject(
        #[MapEntity(mapping: ['userId' => 'uuid'])]
        User $user,
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        $membership = $this->projectMembershipRepository->findOneByProjectAndUser($project, $user);
        if (!$membership instanceof ProjectMembership) {
            throw $this->createNotFoundException();
        }
        $form = $this->csrfOnlyFormFactory->create(
            $this->generateUrl('admin_users_projects_remove', [
                'userId' => $user->getUuid(),
                'projectId' => $project->getUuid(),
            ]),
            'admin_user_project_remove_'.$membership->getId(),
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $actor */
        $actor = $this->getUser();
        try {
            $this->projectMembershipManager->remove($project, $actor, $membership);
            $this->addFlash('success', 'flash.project.member_removed');
        } catch (ProjectAccessException $e) {
            $this->addFlash('error', match ($e->reasonCode) {
                ProjectAccessException::LAST_OWNER => 'flash.project.member_last_owner',
                ProjectAccessException::FORBIDDEN => 'flash.users.project_unlink_forbidden',
                default => 'flash.users.project_unlink_error',
            });
        }

        return $this->redirectToRoute('admin_users_activity', ['id' => $user->getUuid()]);
    }

    /**
     * Change instance role between User and Admin (cannot demote the last admin or self).
     */
    #[Route('/admin/users/{id}/role', name: 'admin_users_role', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function changeRole(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        User $user,
    ): RedirectResponse {
        $form = $this->createForm(AdminUserRoleConfirmType::class, null, [
            'csrf_token_id' => 'admin_user_role_'.$user->getId(),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid form submission.');
        }

        /** @var User $current */
        $current = $this->getUser();
        if ($current->getId() === $user->getId()) {
            $this->addFlash('error', 'flash.users.cannot_change_own_role');

            return $this->redirectToRoute('admin_users');
        }

        /** @var array{role?: string|null} $data */
        $data = $form->getData();
        $role = (string) ($data['role'] ?? '');
        if (!\in_array($role, ['user', 'admin'], true)) {
            $this->addFlash('error', 'flash.users.invalid_role');

            return $this->redirectToRoute('admin_users');
        }

        $makeAdmin = 'admin' === $role;
        $wasAdmin = $this->isAppAdmin($user);
        if ($wasAdmin && !$makeAdmin && $this->countAdmins() <= 1) {
            $this->addFlash('error', 'flash.users.last_admin');

            return $this->redirectToRoute('admin_users');
        }

        $from = $wasAdmin ? 'admin' : 'user';
        $to = $makeAdmin ? 'admin' : 'user';
        if ($from !== $to) {
            $user->setRoles($makeAdmin ? ['ROLE_ADMIN'] : []);
            $this->actionRecorder->record(
                UserActionType::UserRoleChanged,
                $current,
                $user,
                ['from' => $from, 'to' => $to],
            );
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.users.role_updated');
        }

        return $this->redirectToRoute('admin_users');
    }

    /**
     * Toggle UserKit enabled flag (cannot disable self).
     */
    #[Route('/admin/users/{id}/toggle-enabled', name: 'admin_users_toggle_enabled', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function toggleEnabled(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        User $user,
    ): RedirectResponse {
        $form = $this->csrfOnlyFormFactory->create(
            $this->generateUrl('admin_users_toggle_enabled', ['id' => $user->getUuid()]),
            'toggle_user_'.$user->getId(),
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $current */
        $current = $this->getUser();
        if ($current->getId() === $user->getId()) {
            $this->addFlash('error', 'flash.users.cannot_disable_self');

            return $this->redirectToRoute('admin_users');
        }

        $user->setEnabled(!$user->isEnabled());
        $this->actionRecorder->record(
            $user->isEnabled() ? UserActionType::UserEnabled : UserActionType::UserDisabled,
            $current,
            $user,
            ['email' => $user->getEmail()],
        );
        $this->entityManager->flush();

        $this->addFlash(
            'success',
            $user->isEnabled() ? 'flash.users.enabled' : 'flash.users.disabled'
        );

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/users/{id}/export', name: 'admin_users_export', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function export(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        User $user,
    ): Response {
        /** @var User $current */
        $current = $this->getUser();
        $payload = $this->accountDataExporter->export($user);
        $this->actionRecorder->recordAndFlush(
            UserActionType::AccountExported,
            $current,
            $user,
            ['scope' => 'admin'],
        );

        try {
            $json = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new RuntimeException('Unable to encode account export.', 0, $e);
        }

        return new Response($json, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="beacon-account-'.$user->getUuid().'.json"',
        ]);
    }

    #[Route('/admin/users/{id}/anonymize', name: 'admin_users_anonymize', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function anonymize(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        User $user,
    ): RedirectResponse {
        $form = $this->createForm(TypeToConfirmType::class, null, [
            'csrf_token_id' => 'admin_user_anonymize_'.$user->getId(),
        ]);
        $form->submit($request->request->all(), false);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid form submission.');
        }

        /** @var User $current */
        $current = $this->getUser();
        if ($current->getId() === $user->getId()) {
            $this->addFlash('error', 'flash.users.cannot_anonymize_self');

            return $this->redirectToRoute('admin_users');
        }

        try {
            $this->accountAnonymizer->anonymize($user, $current);
        } catch (AccountAnonymizeException $e) {
            $this->addFlash('error', match ($e->reasonCode) {
                AccountAnonymizeException::ALREADY_ANONYMIZED => 'flash.privacy.already_anonymized',
                AccountAnonymizeException::SOLE_OWNER => 'flash.privacy.sole_owner',
                AccountAnonymizeException::LAST_ADMIN => 'flash.privacy.last_admin',
                default => 'flash.privacy.anonymize_failed',
            });

            return $this->redirectToRoute('admin_users');
        }

        $this->addFlash('success', 'flash.users.anonymized');

        return $this->redirectToRoute('admin_users');
    }

    /** Whether the account holds ROLE_ADMIN (instance admin, not project owner). */
    private function isAppAdmin(User $user): bool
    {
        return \in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    /** Number of accounts with ROLE_ADMIN (used to protect the last admin). */
    private function countAdmins(): int
    {
        return $this->userRepository->countAdmins();
    }

    /**
     * @param FormInterface<mixed>|null $invalidCreateForm
     */
    private function renderIndex(
        Request $request,
        ?FormInterface $invalidCreateForm = null,
        bool $openCreate = false,
    ): Response {
        $query = $request->query->getString('q');
        $users = $this->userRepository->findAllForAdminDirectory('' !== $query ? $query : null);
        $toggleEnabledForms = [];
        $roleForms = [];
        $anonymizeForms = [];
        foreach ($users as $user) {
            $userId = $user->getId();
            if (null === $userId) {
                continue;
            }

            $toggleEnabledForms[$userId] = $this->csrfOnlyFormFactory->create(
                $this->generateUrl('admin_users_toggle_enabled', ['id' => $user->getUuid()]),
                'toggle_user_'.$userId,
            )->createView();
            $roleForms[$userId] = $this->createForm(AdminUserRoleConfirmType::class, [
                'role' => \in_array('ROLE_ADMIN', $user->getRoles(), true) ? 'admin' : 'user',
            ], [
                'action' => $this->generateUrl('admin_users_role', ['id' => $user->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'admin_user_role_'.$userId,
            ])->createView();
            $anonymizeForms[$userId] = $this->createForm(TypeToConfirmType::class, [
                'confirmation' => '',
            ], [
                'action' => $this->generateUrl('admin_users_anonymize', ['id' => $user->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'admin_user_anonymize_'.$userId,
            ])->createView();
        }

        $createForm = ($invalidCreateForm ?? $this->buildCreateForm())->createView();

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'q' => $query,
            'searchForm' => $this->getFilterFormFactory->create(AdminSearchType::class, [
                'q' => $query,
            ], [
                'action' => $this->generateUrl('admin_users'),
            ])->createView(),
            'adminCount' => $this->countAdmins(),
            'recentActions' => $this->userActionRepository->findLatest(25),
            'toggleEnabledForms' => $toggleEnabledForms,
            'roleForms' => $roleForms,
            'anonymizeForms' => $anonymizeForms,
            'createForm' => $createForm,
            'open_create' => $openCreate || $request->query->getBoolean('new'),
        ]);
    }

    /** @return FormInterface<mixed> */
    private function buildCreateForm(): FormInterface
    {
        return $this->createForm(AdminUserType::class, [
            'email' => '',
            'displayName' => '',
            'password' => '',
            'role' => 'user',
            'enabled' => true,
        ], [
            'action' => $this->generateUrl('admin_users_new'),
            'method' => 'POST',
        ]);
    }
}
