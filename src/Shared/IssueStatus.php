<?php

declare(strict_types=1);

namespace App\Shared;

enum IssueStatus: string
{
    case Unresolved = 'unresolved';
    case Resolved = 'resolved';
    case Ignored = 'ignored';
}
