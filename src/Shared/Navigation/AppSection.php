<?php

declare(strict_types=1);

namespace App\Shared\Navigation;

/**
 * Application areas switched from the avatar menu; each has its own sidebar menu.
 */
enum AppSection: string
{
    case Dashboard = 'dashboard';
    case Preferences = 'preferences';
    case Administration = 'administration';

    public function menuCode(): string
    {
        return $this->value;
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::Dashboard => 'dashboard_home',
            self::Preferences => 'account_profile',
            self::Administration => 'admin_hub',
        };
    }

    public function labelKey(): string
    {
        return match ($this) {
            self::Dashboard => 'nav.dashboard',
            self::Preferences => 'nav.preferences',
            self::Administration => 'nav.admin',
        };
    }
}
