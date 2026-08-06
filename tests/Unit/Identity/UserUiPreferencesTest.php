<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Entity\Embeddable\UserUiPreferences;
use App\Identity\Entity\User;
use App\Identity\UserDisplayPreferenceDefaults;
use PHPUnit\Framework\TestCase;

final class UserUiPreferencesTest extends TestCase
{
    public function testUserFacadesDelegateToEmbeddable(): void
    {
        $user = new User();
        $user->setPreferredTheme('dark');
        $user->setPreferredContentWidth('full');
        $user->setPushNotificationsEnabled(true);

        self::assertSame('dark', $user->getPreferredTheme());
        self::assertSame('full', $user->getPreferredContentWidth());
        self::assertTrue($user->isPushNotificationsEnabled());
        self::assertSame('dark', $user->getUiPreferences()->getPreferredTheme());
    }

    public function testResetForAnonymizeClearsAllUiPrefs(): void
    {
        $prefs = new UserUiPreferences();
        $prefs->setPreferredLocale('es');
        $prefs->setPreferredTheme('dark');
        $prefs->setPreferredContentWidth('full');
        $prefs->setPreferredUiDensity('compact');
        $prefs->setPreferredFontScale('lg');
        $prefs->setPreferredSidebar('collapsed');
        $prefs->setPreferredCollapsedIssuePanels(['payload']);
        $prefs->markProductTourSeen();
        $prefs->setPushNotificationsEnabled(true);

        $prefs->resetForAnonymize();

        self::assertNull($prefs->getPreferredLocaleRaw());
        self::assertNull($prefs->getPreferredThemeRaw());
        self::assertNull($prefs->getPreferredContentWidthRaw());
        self::assertNull($prefs->getPreferredUiDensityRaw());
        self::assertNull($prefs->getPreferredFontScaleRaw());
        self::assertNull($prefs->getPreferredSidebarRaw());
        self::assertSame(UserDisplayPreferenceDefaults::CONTENT_WIDTH, $prefs->getPreferredContentWidth());
        self::assertFalse($prefs->isProductTourSeen());
        self::assertSame([], $prefs->getProductTourSeenPages());
        self::assertFalse($prefs->isPushNotificationsEnabled());
    }
}
