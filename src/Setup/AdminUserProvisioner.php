<?php

declare(strict_types=1);

namespace App\Setup;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use DateTime;
use Nowo\SiteBackupBundle\Setup\AdminUserProvisionerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates the first instance admin during SiteBackupBundle setup (`admin_user` step).
 */
final readonly class AdminUserProvisioner implements AdminUserProvisionerInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function adminExists(): bool
    {
        return $this->userRepository->count([]) > 0;
    }

    public function createAdmin(array $data): void
    {
        $email = $data['email'];
        $password = $data['password'];
        /** @var list<string> $roles */
        $roles = $data['roles'] ?? ['ROLE_ADMIN'];
        if ([] === $roles) {
            $roles = ['ROLE_ADMIN'];
        }

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($this->displayNameFromEmail($email));
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setPasswordChangedAt(new DateTime());

        $this->userRepository->save($user);
    }

    private function displayNameFromEmail(string $email): string
    {
        $local = strstr($email, '@', true);

        return \is_string($local) && '' !== $local ? $local : 'Admin';
    }
}
