<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\AdminAuditFilter;
use App\Identity\AdminIdentityAudit;
use App\Identity\Entity\User;
use App\Identity\Exception\AccountAnonymizeException;
use App\Identity\Form\AdminUserType;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\AccountAnonymizer;
use App\Identity\Service\AccountDataExporter;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Service\ProjectMembershipManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {
    }

    /** User directory with enable/role controls and a recent-activity strip. */
    #[Route('/admin/users', name: 'admin_users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $request->query->getString('q');

        return $this->render('admin/users/index.html.twig', [
            'users' => $this->userRepository->findAllForAdminDirectory('' !== $query ? $query : null),
            'q' => $query,
            'adminCount' => $this->countAdmins(),
            'recentActions' => $this->userActionRepository->findLatest(25),
        ]);
    }

    /**
     * Create a Beacon account (email, display name, password, instance role).
     */
    #[Route('/admin/users/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(AdminUserType::class, [
            'email' => '',
            'displayName' => '',
            'password' => '',
            'role' => 'user',
            'enabled' => true,
        ]);
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

                return $this->redirectToRoute('admin_users_new');
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

        return $this->render('admin/users/form.html.twig', [
            'form' => $form,
        ]);
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

        return $this->render('admin/users/activity.html.twig', [
            'user' => $user,
            'userAuditActions' => $auditActions,
            'userAuditFilter' => $audit['filter'],
            'actions' => $this->userActionRepository->findForUser(
                $user,
                $auditActions,
                $audit['action'],
                $audit['from'],
                $audit['to'],
                AdminIdentityAudit::TIMELINE_LIMIT,
            ),
            'memberships' => $this->projectMembershipRepository->findByUser($user),
        ]);
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
        if (!$this->isCsrfTokenValid('admin_user_project_remove_'.$membership->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $actor */
        $actor = $this->getUser();
        try {
            $this->projectMembershipManager->remove($project, $actor, $membership);
            $this->addFlash('success', 'flash.project.member_removed');
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->addFlash('error', match ($e->getMessage()) {
                'last_owner' => 'flash.project.member_last_owner',
                'forbidden' => 'flash.users.project_unlink_forbidden',
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
        if (!$this->isCsrfTokenValid('admin_user_role_'.$user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $current */
        $current = $this->getUser();
        if ($current->getId() === $user->getId()) {
            $this->addFlash('error', 'flash.users.cannot_change_own_role');

            return $this->redirectToRoute('admin_users');
        }

        $role = $request->request->getString('role');
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
        if (!$this->isCsrfTokenValid('toggle_user_'.$user->getId(), (string) $request->request->get('_token'))) {
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
        if (!$this->isCsrfTokenValid('admin_user_anonymize_'.$user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
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
        $count = 0;
        foreach ($this->userRepository->findAll() as $user) {
            if ($this->isAppAdmin($user)) {
                ++$count;
            }
        }

        return $count;
    }
}
