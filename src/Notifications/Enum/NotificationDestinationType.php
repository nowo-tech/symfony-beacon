<?php

declare(strict_types=1);

namespace App\Notifications\Enum;

/**
 * Outbound notification destination types.
 */
enum NotificationDestinationType: string
{
    case Slack = 'slack';
    case Discord = 'discord';
    case Teams = 'teams';
    case Telegram = 'telegram';
    case Email = 'email';
    case Http = 'http';
}
