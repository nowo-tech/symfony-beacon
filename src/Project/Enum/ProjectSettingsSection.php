<?php

declare(strict_types=1);

namespace App\Project\Enum;

use App\Project\Access\ProjectAccess;

/**
 * Project Settings UI sections (route slugs under /projects/{id}/settings/{section}).
 */
enum ProjectSettingsSection: string
{
    case General = 'general';
    case Access = 'access';
    case Alerts = 'alerts';
    case Data = 'data';
    case Danger = 'danger';

    public function tabLabelKey(): string
    {
        return 'project.settings.tab.'.$this->value;
    }

    /**
     * Whether this tab should be offered for the given access.
     */
    public function isVisibleFor(ProjectAccess $access): bool
    {
        return match ($this) {
            self::General, self::Data => $access->canManageSettings(),
            self::Access, self::Alerts => true,
            self::Danger => $access->canManageSettings() || $access->canDeleteProject(),
        };
    }

    /**
     * @return list<self>
     */
    public static function visibleFor(ProjectAccess $access): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $section): bool => $section->isVisibleFor($access),
        ));
    }

    public static function defaultFor(ProjectAccess $access): self
    {
        $visible = self::visibleFor($access);

        return $visible[0] ?? self::Access;
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
