<?php

declare(strict_types=1);

namespace App\Issues\Enum;

/**
 * Triage priority for a grouped issue.
 */
enum IssuePriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
