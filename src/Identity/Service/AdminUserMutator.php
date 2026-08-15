<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\UserActionType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Application mutations for Administration → Users (create / role / enable).
 *
 * Controllers own HTTP forms, flashes, and redirects.
 */
final readonly class AdminUserMutator
{
    public function __construct(
        private UserRepository $userRepository,
        private UserActionRecorder $actionRecorder,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @return 'created'|'email_taken'
     */
    public function create(
        User $actor,
        string $email,
        string $displayName,
        string $plainPassword,
        string $role,
        bool $enabled,
    ): string {
        $email = strtolower(trim($email));
        $displayName = trim($displayName);
        if ($this->userRepository->findOneByEmail($email) instanceof User) {
            return 'email_taken';
        }

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($displayName);
        $user->setRoles('admin' === $role ? ['ROLE_ADMIN'] : []);
        $user->setEnabled($enabled);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
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

        return 'created';
    }

    /**
     * @return 'updated'|'unchanged'|'cannot_change_own'|'invalid_role'|'last_admin'
     */
    public function changeInstanceRole(User $actor, User $user, string $role): string
    {
        if ($actor->getId() === $user->getId()) {
            return 'cannot_change_own';
        }
        if (!\in_array($role, ['user', 'admin'], true)) {
            return 'invalid_role';
        }

        $makeAdmin = 'admin' === $role;
        $wasAdmin = $this->isAppAdmin($user);
        if ($wasAdmin && !$makeAdmin && $this->countAdmins() <= 1) {
            return 'last_admin';
        }

        $from = $wasAdmin ? 'admin' : 'user';
        $to = $makeAdmin ? 'admin' : 'user';
        if ($from === $to) {
            return 'unchanged';
        }

        $user->setRoles($makeAdmin ? ['ROLE_ADMIN'] : []);
        $this->actionRecorder->record(
            UserActionType::UserRoleChanged,
            $actor,
            $user,
            ['from' => $from, 'to' => $to],
        );
        $this->entityManager->flush();

        return 'updated';
    }

    /**
     * @return 'enabled'|'disabled'|'cannot_disable_self'
     */
    public function toggleEnabled(User $actor, User $user): string
    {
        if ($actor->getId() === $user->getId()) {
            return 'cannot_disable_self';
        }

        $user->setEnabled(!$user->isEnabled());
        $this->actionRecorder->record(
            $user->isEnabled() ? UserActionType::UserEnabled : UserActionType::UserDisabled,
            $actor,
            $user,
            ['email' => $user->getEmail()],
        );
        $this->entityManager->flush();

        return $user->isEnabled() ? 'enabled' : 'disabled';
    }

    private function isAppAdmin(User $user): bool
    {
        return \in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    private function countAdmins(): int
    {
        return $this->userRepository->countAdmins();
    }
}
