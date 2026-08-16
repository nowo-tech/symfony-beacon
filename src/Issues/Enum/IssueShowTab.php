<?php

declare(strict_types=1);

namespace App\Issues\Enum;

/**
 * Issue detail UI tabs (route slugs under /projects/{projectId}/issues/{id}/{tab}).
 */
enum IssueShowTab: string
{
    case Main = 'main';
    case Similar = 'similar';
    case History = 'history';

    public function tabLabelKey(): string
    {
        return match ($this) {
            self::Main => 'issues.tab_main',
            self::Similar => 'issues.similar_title',
            self::History => 'issues.history_title',
        };
    }

    /**
     * Route requirement pattern for {tab}.
     */
    public static function routeRequirement(): string
    {
        return implode('|', array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        ));
    }
}
