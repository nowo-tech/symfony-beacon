<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\PasswordHistory;
use App\Identity\Entity\User;
use App\Identity\Exception\AccountAnonymizeException;
use App\Identity\Repository\UserRepository;
use App\Identity\UserActionType;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Shared\ProjectRole;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Entity\SocialLoginAccount;
use Nowo\AuthKitBundle\Repository\SocialLoginAccountRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Scrubs personal identifiers and disables login (GDPR soft-delete). App-owned — not anonymize-bundle.
 */
final readonly class AccountAnonymizer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private ProjectMembershipRepository $projectMembershipRepository,
        private PushSubscriptionRepository $pushSubscriptionRepository,
        private SocialLoginAccountRepository $socialLoginAccountRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private UserActionRecorder $actionRecorder,
    ) {
    }

    /**
     * @throws AccountAnonymizeException
     */
    public function anonymize(User $subject, User $actor): void
    {
        if ($subject->isAnonymized()) {
            throw new AccountAnonymizeException(AccountAnonymizeException::ALREADY_ANONYMIZED);
        }

        $soleOwnerProjects = $this->soleOwnerProjects($subject);
        if ([] !== $soleOwnerProjects) {
            throw new AccountAnonymizeException(AccountAnonymizeException::SOLE_OWNER, $soleOwnerProjects);
        }

        if ($this->isLastAdmin($subject)) {
            throw new AccountAnonymizeException(AccountAnonymizeException::LAST_ADMIN);
        }

        $previousEmail = $subject->getEmail();
        $uuid = $subject->getUuid();

        $subject->setEmail('anonymized-'.$uuid.'@invalid.local');
        $subject->setDisplayName('Anonymized user');
        $subject->setSlackUserId(null);
        $subject->setPhone(null);
        $subject->setPhoneVerifiedAt(null);
        $subject->setEnabled(false);
        $subject->setAnonymizedAt(new DateTimeImmutable());
        $subject->setPasswordResetToken(null);
        $subject->setPasswordResetExpiresAt(null);
        $subject->setPushNotificationsEnabled(false);
        $subject->setPreferredLocale(null);
        $subject->setPreferredTheme(null);
        $subject->setPreferredMotion(null);
        $subject->setPreferredContrast(null);
        $subject->setRoles([]);

        $random = bin2hex(random_bytes(32));
        $subject->setPassword($this->passwordHasher->hashPassword($subject, $random));
        $subject->eraseCredentials();

        foreach ($subject->getPasswordHistory()->toArray() as $history) {
            if ($history instanceof PasswordHistory) {
                $subject->removePasswordHistory($history);
                $this->entityManager->remove($history);
            }
        }

        foreach ($this->pushSubscriptionRepository->findByUser($subject) as $subscription) {
            $this->entityManager->remove($subscription);
        }

        $userId = $subject->getId();
        if (null !== $userId) {
            /** @var list<SocialLoginAccount> $socialAccounts */
            $socialAccounts = $this->socialLoginAccountRepository->findBy([
                'userClass' => User::class,
                'userId' => (string) $userId,
            ]);
            foreach ($socialAccounts as $account) {
                $this->entityManager->remove($account);
            }
        }

        $this->actionRecorder->record(
            UserActionType::UserAnonymized,
            $actor,
            $subject,
            [
                'previous_email_domain' => $this->emailDomain($previousEmail),
                'uuid' => $uuid,
            ],
        );

        $this->entityManager->flush();
    }

    /**
     * @return list<array{uuid: string, name: string}>
     */
    public function soleOwnerProjects(User $user): array
    {
        $blocked = [];
        foreach ($this->projectMembershipRepository->findByUser($user) as $membership) {
            if (ProjectRole::Owner !== $membership->getRole()) {
                continue;
            }
            $project = $membership->getProject();
            $owners = 0;
            foreach ($this->projectMembershipRepository->findBy(['project' => $project, 'role' => ProjectRole::Owner]) as $ownerMembership) {
                ++$owners;
            }
            if ($owners <= 1) {
                $blocked[] = [
                    'uuid' => $project->getUuid(),
                    'name' => $project->getName(),
                ];
            }
        }

        return $blocked;
    }

    public function isLastAdmin(User $user): bool
    {
        if (!\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return false;
        }

        $admins = 0;
        foreach ($this->userRepository->findAll() as $candidate) {
            if ($candidate->isAnonymized()) {
                continue;
            }
            if (\in_array('ROLE_ADMIN', $candidate->getRoles(), true)) {
                ++$admins;
            }
        }

        return $admins <= 1;
    }

    private function emailDomain(string $email): string
    {
        $at = strrpos($email, '@');
        if (false === $at) {
            return 'unknown';
        }

        return substr($email, $at + 1);
    }
}
