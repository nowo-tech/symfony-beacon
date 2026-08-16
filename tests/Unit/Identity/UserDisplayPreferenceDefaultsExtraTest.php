<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Entity\User;
use App\Identity\Entity\Embeddable\UserUiPreferences;
use App\Identity\UserDisplayPreferenceDefaults;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class UserDisplayPreferenceDefaultsExtraTest extends TestCase
{
    public function testApplyMissingFillsRemainingNullColumnsAndFallsBackLocale(): void
    {
        $user = new User();
        $user->setPreferredLocale(null);
        $this->forcePreferenceFieldToNull($user, 'preferredContentWidth');
        $this->forcePreferenceFieldToNull($user, 'preferredUiDensity');
        $this->forcePreferenceFieldToNull($user, 'preferredFontScale');
        $this->forcePreferenceFieldToNull($user, 'preferredSidebar');

        UserDisplayPreferenceDefaults::applyMissing($user, '   ');

        self::assertSame(UserDisplayPreferenceDefaults::LOCALE, $user->getPreferredLocaleRaw());
        self::assertSame(UserDisplayPreferenceDefaults::CONTENT_WIDTH, $user->getPreferredContentWidthRaw());
        self::assertSame(UserDisplayPreferenceDefaults::UI_DENSITY, $user->getPreferredUiDensityRaw());
        self::assertSame(UserDisplayPreferenceDefaults::FONT_SCALE, $user->getPreferredFontScaleRaw());
        self::assertSame(UserDisplayPreferenceDefaults::SIDEBAR, $user->getPreferredSidebarRaw());
    }

    private function forcePreferenceFieldToNull(User $user, string $property): void
    {
        /** @var UserUiPreferences $preferences */
        $preferences = new ReflectionProperty($user, 'uiPreferences')->getValue($user);
        new ReflectionProperty($preferences, $property)->setValue($preferences, null);
    }
}
