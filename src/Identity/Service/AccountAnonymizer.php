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
use App\Project\Enum\ProjectRole;
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
        $subject->getUiPreferences()->resetForAnonymize();
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
        $ownerMemberships = [];
        foreach ($this->projectMembershipRepository->findByUser($user) as $membership) {
            if (ProjectRole::Owner === $membership->getRole()) {
                $ownerMemberships[] = $membership;
            }
        }
        if ([] === $ownerMemberships) {
            return [];
        }

        $projectIds = [];
        foreach ($ownerMemberships as $membership) {
            $projectId = $membership->getProject()->getId();
            if (null !== $projectId) {
                $projectIds[] = $projectId;
            }
        }

        $ownerCounts = $this->projectMembershipRepository->countOwnersByProjectIds($projectIds);

        $blocked = [];
        foreach ($ownerMemberships as $membership) {
            $project = $membership->getProject();
            $projectId = $project->getId();
            if (null === $projectId || ($ownerCounts[$projectId] ?? 0) > 1) {
                continue;
            }
            $blocked[] = [
                'uuid' => $project->getUuid(),
                'name' => $project->getName(),
            ];
        }

        return $blocked;
    }

    public function isLastAdmin(User $user): bool
    {
        if (!\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return false;
        }

        return $this->userRepository->countAdmins(excludeAnonymized: true) <= 1;
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
