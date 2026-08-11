<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use DateTime;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates disabled Identity users for portable project-config import (089).
 *
 * Keeps user provisioning in Identity so Project portability does not own password hashing.
 */
final readonly class PortableUserProvisioner
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Persist a disabled account with a random unusable password (no flush by default).
     */
    public function createDisabledUser(string $email, string $displayName, bool $flush = false): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName('' !== $displayName ? $displayName : $email);
        $user->setRoles([]);
        $user->setEnabled(false);
        $plain = bin2hex(random_bytes(24));
        $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
        $user->setPasswordChangedAt(new DateTime());
        $this->userRepository->save($user, $flush);

        return $user;
    }
}
