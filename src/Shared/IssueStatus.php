<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Lifecycle status of a grouped issue.
 */
enum IssueStatus: string
{
    case Unresolved = 'unresolved';
    case Resolved = 'resolved';
    case Ignored = 'ignored';
}
