<?php

declare(strict_types=1);

namespace App\Issues;

/**
 * Kinds of entries recorded on an issue (assignment / status workflow).
 */
enum IssueHistoryKind: string
{
    case AssigneeChanged = 'assignee_changed';
    case StatusChanged = 'status_changed';
}
