<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Service\ProjectMembershipManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
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
    ) {
    }

    /** User directory with enable/role controls and a recent-activity strip. */
    #[Route('/admin/users', name: 'admin_users', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $this->userRepository->findAllForAdminDirectory(),
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
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_user_new', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $email = strtolower(trim($request->request->getString('email')));
            $displayName = trim($request->request->getString('display_name'));
            $password = $request->request->getString('password');
            $role = $request->request->getString('role');
            $enabled = $request->request->getBoolean('enabled', true);

            if ('' === $email || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'flash.users.invalid_email');

                return $this->redirectToRoute('admin_users_new');
            }
            if ('' === $displayName) {
                $this->addFlash('error', 'flash.users.name_required');

                return $this->redirectToRoute('admin_users_new');
            }
            if (\strlen($password) < 8) {
                $this->addFlash('error', 'flash.users.password_too_short');

                return $this->redirectToRoute('admin_users_new');
            }
            if ($this->userRepository->findOneByEmail($email) instanceof User) {
                $this->addFlash('error', 'flash.users.email_taken');

                return $this->redirectToRoute('admin_users_new');
            }
            if (!\in_array($role, ['user', 'admin'], true)) {
                $this->addFlash('error', 'flash.users.invalid_role');

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

        return $this->render('admin/users/form.html.twig');
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
    ): Response {
        return $this->render('admin/users/activity.html.twig', [
            'user' => $user,
            'actions' => $this->userActionRepository->findForUser($user),
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
