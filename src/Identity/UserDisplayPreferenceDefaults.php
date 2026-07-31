<?php

declare(strict_types=1);

namespace App\Identity;

use App\Identity\Entity\User;

/**
 * Canonical display preference defaults for new accounts and null legacy rows.
 */
final class UserDisplayPreferenceDefaults
{
    public const string THEME = 'light';

    public const string MOTION = 'system';

    public const string CONTRAST = 'system';

    public const string CONTENT_WIDTH = 'content';

    public const string UI_DENSITY = 'comfortable';

    public const string FONT_SCALE = 'md';

    public const string SIDEBAR = 'expanded';

    /** Fallback when `%default_locale%` is unavailable (entity getters). */
    public const string LOCALE = 'en';

    /**
     * Fill null preference columns so new users always persist concrete values.
     */
    public static function applyMissing(User $user, string $defaultLocale): void
    {
        $locale = strtolower(trim($defaultLocale));
        if ('' === $locale) {
            $locale = self::LOCALE;
        }

        if (null === $user->getPreferredLocaleRaw()) {
            $user->setPreferredLocale($locale);
        }
        if (null === $user->getPreferredThemeRaw()) {
            $user->setPreferredTheme(self::THEME);
        }
        if (null === $user->getPreferredMotionRaw()) {
            $user->setPreferredMotion(self::MOTION);
        }
        if (null === $user->getPreferredContrastRaw()) {
            $user->setPreferredContrast(self::CONTRAST);
        }
        if (null === $user->getPreferredContentWidthRaw()) {
            $user->setPreferredContentWidth(self::CONTENT_WIDTH);
        }
        if (null === $user->getPreferredUiDensityRaw()) {
            $user->setPreferredUiDensity(self::UI_DENSITY);
        }
        if (null === $user->getPreferredFontScaleRaw()) {
            $user->setPreferredFontScale(self::FONT_SCALE);
        }
        if (null === $user->getPreferredSidebarRaw()) {
            $user->setPreferredSidebar(self::SIDEBAR);
        }
    }
}
