<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Selectable alert categories for notification destinations.
 */
final class NotificationCategories
{
    public const string N_PLUS_ONE = 'n_plus_one';

    public const string ISSUE_RESOLVED = 'issue.resolved';

    public const string ISSUE_REOPENED = 'issue.reopened';

    public const string ISSUE_ASSIGNED = 'issue.assigned';

    public const string ISSUE_COMMENTED = 'issue.commented';

    public const string ISSUE_DUPLICATED = 'issue.duplicated';

    /** @var list<string> */
    public const array ISSUE_LEVELS = ['fatal', 'error', 'warning', 'info', 'debug'];

    /** @var list<string> */
    public const array LIFECYCLE = [
        self::ISSUE_RESOLVED,
        self::ISSUE_REOPENED,
        self::ISSUE_ASSIGNED,
        self::ISSUE_COMMENTED,
        self::ISSUE_DUPLICATED,
    ];

    /** @var list<string> */
    public const array ALL = [
        'fatal',
        'error',
        'warning',
        'info',
        'debug',
        self::N_PLUS_ONE,
        self::ISSUE_RESOLVED,
        self::ISSUE_REOPENED,
        self::ISSUE_ASSIGNED,
        self::ISSUE_COMMENTED,
        self::ISSUE_DUPLICATED,
    ];

    /**
     * @param list<string> $categories
     *
     * @return list<string>
     */
    public static function sanitize(array $categories): array
    {
        $allowed = array_fill_keys(self::ALL, true);
        $out = [];
        foreach ($categories as $category) {
            if (!\is_string($category) || !isset($allowed[$category])) {
                continue;
            }
            $out[$category] = true;
        }

        return array_keys($out);
    }

    public static function isIssueLevel(string $category): bool
    {
        return \in_array($category, self::ISSUE_LEVELS, true);
    }

    public static function isLifecycle(string $category): bool
    {
        return \in_array($category, self::LIFECYCLE, true);
    }
}
