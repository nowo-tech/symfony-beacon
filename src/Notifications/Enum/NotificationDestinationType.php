<?php

declare(strict_types=1);

namespace App\Notifications\Enum;

/**
 * Outbound notification destination types for v1.
 */
enum NotificationDestinationType: string
{
    case Slack = 'slack';
    case Http = 'http';
}
