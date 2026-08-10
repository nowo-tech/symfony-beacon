<?php

declare(strict_types=1);

namespace App\Identity\Service;

/**
 * Allowed permission category machine slugs for the shared permission catalog (REQ-RBAC-008).
 *
 * Labels resolve via permissions.category.<slug>.name|description — not stored here.
 * Built-in rows are project membership (`project.*`) groupings; custom rows may use general/custom.
 */
final class InstancePermissionCategoryCatalog
{
    /** @var list<string> */
    public const array SLUGS = [
        'access',
        'issues',
        'collaboration',
        'integration',
        'settings',
        'danger',
        'general',
        'custom',
    ];

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return self::SLUGS;
    }

    /**
     * ChoiceType map: translation key → machine slug.
     *
     * @return array<string, string>
     */
    public static function formChoices(): array
    {
        $choices = [];
        foreach (self::SLUGS as $slug) {
            $choices['permissions.category.'.$slug.'.name'] = $slug;
        }

        return $choices;
    }

    public static function isKnown(string $slug): bool
    {
        return \in_array(strtolower(trim($slug)), self::SLUGS, true);
    }
}
