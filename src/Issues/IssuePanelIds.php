<?php

declare(strict_types=1);

namespace App\Issues;

/**
 * Stable ids for collapsible panels on issue / event detail pages.
 */
final class IssuePanelIds
{
    public const string HIGHLIGHTS = 'highlights';
    public const string STACKTRACE = 'stacktrace';
    public const string MESSAGE = 'message';
    public const string BREADCRUMBS = 'breadcrumbs';
    public const string REQUEST = 'request';
    public const string TAGS = 'tags';
    public const string CONTEXTS = 'contexts';
    public const string EXTRA = 'extra';
    public const string RAW = 'raw';
    public const string DETAILS = 'details';
    public const string TRIAGE = 'triage';
    public const string ASSIGNEE = 'assignee';
    public const string DUPLICATE = 'duplicate';
    public const string ACTIVITY = 'activity';
    public const string RECENT_EVENTS = 'recent_events';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::HIGHLIGHTS,
            self::STACKTRACE,
            self::MESSAGE,
            self::BREADCRUMBS,
            self::REQUEST,
            self::TAGS,
            self::CONTEXTS,
            self::EXTRA,
            self::RAW,
            self::DETAILS,
            self::TRIAGE,
            self::ASSIGNEE,
            self::DUPLICATE,
            self::ACTIVITY,
            self::RECENT_EVENTS,
        ];
    }

    /**
     * Defaults when the user has not saved preferences yet (first browser visit).
     *
     * @return list<string>
     */
    public static function defaultCollapsed(): array
    {
        return [self::RAW, self::EXTRA];
    }

    /**
     * @param list<mixed> $ids
     *
     * @return list<string>
     */
    public static function sanitize(array $ids): array
    {
        $allowed = array_fill_keys(self::all(), true);
        $out = [];
        foreach ($ids as $id) {
            if (!\is_string($id)) {
                continue;
            }
            $id = trim($id);
            if (isset($allowed[$id])) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }
}
