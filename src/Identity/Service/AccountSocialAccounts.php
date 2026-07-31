<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\User;
use Nowo\AuthKitBundle\Entity\SocialLoginAccount;
use Nowo\AuthKitBundle\Repository\SocialLoginAccountRepository;
use Nowo\AuthKitBundle\SocialLogin\SocialLoginGate;

/**
 * Read-only social account links for Account → Security (`037`).
 *
 * Unlink is not offered — AuthKit has no public unlink API.
 */
final readonly class AccountSocialAccounts
{
    public function __construct(
        private SocialLoginGate $socialLoginGate,
        private SocialLoginAccountRepository $socialLoginAccountRepository,
    ) {
    }

    public function isSocialLoginEnabled(): bool
    {
        return $this->socialLoginGate->isEnabled();
    }

    /**
     * @return list<SocialLoginAccount>
     */
    public function linkedFor(User $user): array
    {
        $id = $user->getId();
        if (null === $id) {
            return [];
        }

        /** @var list<SocialLoginAccount> $rows */
        $rows = $this->socialLoginAccountRepository->findBy(
            [
                'userClass' => User::class,
                'userId' => (string) $id,
            ],
            ['provider' => 'ASC', 'id' => 'ASC'],
        );

        return $rows;
    }
}
