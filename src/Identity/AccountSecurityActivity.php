<?php

declare(strict_types=1);

namespace App\Identity;

/**
 * End-user security activity allowlist for Account → Security (`037`).
 *
 * Admin timelines use {@see AdminIdentityAudit}; this list is auth-only.
 */
final class AccountSecurityActivity
{
    public const int TIMELINE_LIMIT = 50;

    /**
     * @return list<UserActionType>
     */
    public static function actionTypes(): array
    {
        return [
            UserActionType::MagicLoginRequested,
            UserActionType::MagicLoginConsumed,
            UserActionType::PasswordResetRequested,
        ];
    }
}
