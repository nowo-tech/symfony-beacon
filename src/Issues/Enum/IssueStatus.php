<?php

declare(strict_types=1);

namespace App\Issues\Enum;

/**
 * Lifecycle status of a grouped issue.
 */
enum IssueStatus: string
{
    case Unresolved = 'unresolved';
    case Resolved = 'resolved';
    case Ignored = 'ignored';
}
