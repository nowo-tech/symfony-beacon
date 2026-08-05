<?php

declare(strict_types=1);

namespace App\Shared\Settings;

/**
 * Ops defaults admin UI sections (route slugs + form field groups).
 */
enum OpsDefaultsSection: string
{
    case Governance = 'governance';
    case Ingest = 'ingest';
    case Metrics = 'metrics';
    case Inbound = 'inbound';
    case Notifications = 'notifications';

    /**
     * Translation key under ops_defaults.section.* for the tab label.
     */
    public function tabLabelKey(): string
    {
        return 'ops_defaults.section.'.$this->value;
    }

    /**
     * Translation key under ops_defaults.section.*_help for the section intro.
     */
    public function helpKey(): string
    {
        return 'ops_defaults.section.'.$this->value.'_help';
    }

    /**
     * Route requirement pattern for {section}.
     */
    public static function routeRequirement(): string
    {
        return implode('|', array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        ));
    }
}
