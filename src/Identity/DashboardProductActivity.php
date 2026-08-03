<?php

declare(strict_types=1);

namespace App\Identity;

/**
 * Allowlisted {@see UserActionType} values for the dashboard Recent activity panel.
 */
final class DashboardProductActivity
{
    /**
     * @return list<UserActionType>
     */
    public static function types(): array
    {
        return [
            UserActionType::ProjectOpened,
            UserActionType::ProjectSettingsViewed,
            UserActionType::ProjectCreated,
            UserActionType::IssueOpened,
            UserActionType::IssueAssigned,
            UserActionType::IssueStatusChanged,
            UserActionType::IssuePriorityChanged,
            UserActionType::IssueCommented,
            UserActionType::IssueMarkedDuplicate,
            UserActionType::IssueMerged,
            UserActionType::EventOpened,
            UserActionType::PerformanceOpened,
            UserActionType::AnalyticsOpened,
        ];
    }
}
