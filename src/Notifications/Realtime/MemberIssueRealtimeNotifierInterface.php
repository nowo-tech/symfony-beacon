<?php

declare(strict_types=1);

namespace App\Notifications\Realtime;

use App\Issues\Entity\Issue;
use App\Project\Entity\Project;

/**
 * Publishes live updates / queues push for project members on new issues.
 */
interface MemberIssueRealtimeNotifierInterface
{
    public function notifyNewIssue(Project $project, Issue $issue): void;
}
