<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\AccountSecurityActivity;
use App\Identity\Entity\PasswordHistory;
use App\Identity\Entity\User;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Project\Repository\ProjectMembershipRepository;
use DateTimeInterface;
use Nowo\AuthKitBundle\Entity\SocialLoginAccount;

/**
 * Builds a scrubbed JSON document for GDPR account export (`beacon-account-export/v1`).
 */
final readonly class AccountDataExporter
{
    public function __construct(
        private ProjectMembershipRepository $projectMembershipRepository,
        private UserGroupMembershipRepository $userGroupMembershipRepository,
        private UserActionRepository $userActionRepository,
        private PushSubscriptionRepository $pushSubscriptionRepository,
        private AccountSocialAccounts $accountSocialAccounts,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $memberships = [];
        foreach ($this->projectMembershipRepository->findByUser($user) as $membership) {
            $project = $membership->getProject();
            $memberships[] = [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
                'role' => $membership->getRole()->value,
            ];
        }

        $activity = [];
        foreach ($this->userActionRepository->findForUser(
            $user,
            AccountSecurityActivity::actionTypes(),
            null,
            null,
            null,
            AccountSecurityActivity::TIMELINE_LIMIT,
        ) as $row) {
            $activity[] = [
                'action' => $row->getAction()->value,
                'created_at' => $this->formatDate($row->getCreatedAt()),
                'context' => $row->getContext(),
            ];
        }

        $passwordEntries = [];
        foreach ($user->getPasswordHistory() as $history) {
            if (!$history instanceof PasswordHistory) {
                continue;
            }
            $passwordEntries[] = [
                'changed_at' => $this->formatDate($history->getCreatedAt()),
            ];
        }

        $social = [];
        foreach ($this->accountSocialAccounts->linkedFor($user) as $account) {
            $social[] = $this->mapSocialAccount($account);
        }

        $groups = [];
        foreach ($this->userGroupMembershipRepository->findByUser($user) as $membership) {
            $group = $membership->getUserGroup();
            if (null === $group) {
                continue;
            }
            $groups[] = [
                'group_uuid' => $group->getUuid(),
                'group_name' => $group->getName(),
            ];
        }

        return [
            'schema' => 'beacon-account-export/v1',
            'exported_at' => (new \DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'account' => [
                'uuid' => $user->getUuid(),
                'email' => $user->getEmail(),
                'display_name' => $user->getDisplayName(),
                'roles' => array_values(array_filter(
                    $user->getRoles(),
                    static fn (string $role): bool => 'ROLE_USER' !== $role,
                )),
                'enabled' => $user->isEnabled(),
                'anonymized_at' => $this->formatDate($user->getAnonymizedAt()),
                'preferred_locale' => $user->getPreferredLocale(),
                'preferred_theme' => $user->getPreferredTheme(),
                'preferred_content_width' => $user->getPreferredContentWidth(),
                'preferred_ui_density' => $user->getPreferredUiDensity(),
                'preferred_motion' => $user->getPreferredMotion(),
                'preferred_font_scale' => $user->getPreferredFontScale(),
                'preferred_contrast' => $user->getPreferredContrast(),
                'preferred_sidebar' => $user->getPreferredSidebar(),
                'push_notifications_enabled' => $user->isPushNotificationsEnabled(),
                'created_at' => $this->formatDate($user->getCreatedAt()),
                'updated_at' => $this->formatDate($user->getUpdatedAt()),
                'last_activity_at' => $this->formatDate($user->getLastActivityAt()),
                'password_changed_at' => $this->formatDate($user->getPasswordChangedAt()),
            ],
            'project_memberships' => $memberships,
            'group_memberships' => $groups,
            'security_activity' => $activity,
            'password_history' => [
                'count' => \count($passwordEntries),
                'entries' => $passwordEntries,
            ],
            'social_accounts' => $social,
            'push_subscriptions_count' => \count($this->pushSubscriptionRepository->findByUser($user)),
            'notes' => [
                'events_retention' => 'Project ingest events and issues are not included; they remain project data until retention purge.',
            ],
        ];
    }

    /**
     * @return array{provider: string, linked_at: ?string, provider_email: ?string}
     */
    private function mapSocialAccount(SocialLoginAccount $account): array
    {
        return [
            'provider' => $account->getProvider(),
            'linked_at' => $this->formatDate($account->getCreatedAt()),
            'provider_email' => $account->getEmail(),
        ];
    }

    private function formatDate(?DateTimeInterface $date): ?string
    {
        return $date?->format(DateTimeInterface::ATOM);
    }
}
