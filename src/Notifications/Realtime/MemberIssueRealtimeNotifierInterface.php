<?php

declare(strict_types=1);

namespace App\Notifications\Realtime;

use App\Issues\Entity\Issue;
use App\Notifications\Enum\MemberAlertEvent;
use App\Project\Entity\Project;

/**
 * Publishes live updates / queues push for eligible project members.
 */
interface MemberIssueRealtimeNotifierInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function notify(MemberAlertEvent $event, Project $project, Issue $issue, array $payload): void;
}
