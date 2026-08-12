<?php

declare(strict_types=1);

namespace App\Notifications\Realtime;

/**
 * Mercure topic helpers for member issue alerts.
 */
final class IssueRealtimeTopics
{
    public static function forProject(string $projectUuid): string
    {
        return \sprintf('/projects/%s/issues', $projectUuid);
    }

    public static function forUser(string $userUuid): string
    {
        return \sprintf('/users/%s/member-alerts', $userUuid);
    }
}
