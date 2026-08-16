<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Entity\Embeddable\UserUiPreferences;
use App\Identity\Entity\User;
use App\Identity\Tour\ProductTourPage;
use App\Identity\UserDisplayPreferenceDefaults;
use App\Issues\IssuePanelIds;
use PHPUnit\Framework\TestCase;

final class UserUiPreferencesTest extends TestCase
{
    public function testNewUserEnablesPushNotificationsByDefault(): void
    {
        $user = new User();

        self::assertTrue($user->isPushNotificationsEnabled());
        self::assertTrue($user->isMemberAlertsEnabled());
    }

    public function testUserFacadesDelegateToEmbeddable(): void
    {
        $user = new User();
        $user->setPreferredTheme('dark');
        $user->setPreferredContentWidth('full');
        $user->setPushNotificationsEnabled(false);

        self::assertSame('dark', $user->getPreferredTheme());
        self::assertSame('full', $user->getPreferredContentWidth());
        self::assertFalse($user->isPushNotificationsEnabled());
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
        $prefs->setPushNotificationsEnabled(false);

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
        self::assertTrue($prefs->isPushNotificationsEnabled());
        self::assertTrue($prefs->isMemberAlertsEnabled());
    }

    public function testEmbeddableNormalizesPreferencesAndTourState(): void
    {
        $prefs = new UserUiPreferences();
        $prefs
            ->setPreferredLocale(' ES ')
            ->setPreferredTheme('DARK')
            ->setPreferredContentWidth('FULL')
            ->setPreferredUiDensity('COMPACT')
            ->setPreferredMotion('REDUCE')
            ->setPreferredFontScale('LG')
            ->setPreferredContrast('MORE')
            ->setPreferredSidebar('COLLAPSED')
            ->setPreferredCollapsedIssuePanels([
                IssuePanelIds::RAW,
                ' invalid ',
                IssuePanelIds::EXTRA,
                IssuePanelIds::RAW,
            ])
            ->markTourPageSeen(ProductTourPage::Dashboard->value)
            ->markTourPageSeen('invalid-page')
            ->setMemberAlertsEnabled(false);

        self::assertSame('es', $prefs->getPreferredLocale());
        self::assertSame('dark', $prefs->getPreferredTheme());
        self::assertSame('full', $prefs->getPreferredContentWidth());
        self::assertSame('compact', $prefs->getPreferredUiDensity());
        self::assertSame('reduce', $prefs->getPreferredMotion());
        self::assertSame('lg', $prefs->getPreferredFontScale());
        self::assertSame('more', $prefs->getPreferredContrast());
        self::assertSame('collapsed', $prefs->getPreferredSidebar());
        self::assertSame([IssuePanelIds::RAW, IssuePanelIds::EXTRA], $prefs->getPreferredCollapsedIssuePanels());
        self::assertTrue($prefs->hasSeenTourPage(ProductTourPage::Dashboard->value));
        self::assertSame(
            [ProductTourPage::ProjectIssues->value, ProductTourPage::Admin->value],
            $prefs->getEnabledProductTourPages(),
        );
        self::assertFalse($prefs->isMemberAlertsEnabled());

        $prefs
            ->setPreferredLocale('   ')
            ->setPreferredTheme('sepia')
            ->setPreferredContentWidth('wide')
            ->setPreferredUiDensity('dense')
            ->setPreferredMotion('cinema')
            ->setPreferredFontScale('xl')
            ->setPreferredContrast('low')
            ->setPreferredSidebar('mini')
            ->setPreferredCollapsedIssuePanels(null)
            ->clearProductTourSeen()
            ->syncEnabledProductTourPages([ProductTourPage::Dashboard->value, 'bogus']);

        self::assertSame(UserDisplayPreferenceDefaults::LOCALE, $prefs->getPreferredLocale());
        self::assertSame(UserDisplayPreferenceDefaults::THEME, $prefs->getPreferredTheme());
        self::assertSame(UserDisplayPreferenceDefaults::CONTENT_WIDTH, $prefs->getPreferredContentWidth());
        self::assertSame(UserDisplayPreferenceDefaults::UI_DENSITY, $prefs->getPreferredUiDensity());
        self::assertSame(UserDisplayPreferenceDefaults::MOTION, $prefs->getPreferredMotion());
        self::assertSame(UserDisplayPreferenceDefaults::FONT_SCALE, $prefs->getPreferredFontScale());
        self::assertSame(UserDisplayPreferenceDefaults::CONTRAST, $prefs->getPreferredContrast());
        self::assertSame(UserDisplayPreferenceDefaults::SIDEBAR, $prefs->getPreferredSidebar());
        self::assertSame(IssuePanelIds::defaultCollapsed(), $prefs->getPreferredCollapsedIssuePanels());
        self::assertFalse($prefs->isProductTourSeen());
        self::assertSame(
            [ProductTourPage::ProjectIssues->value, ProductTourPage::Admin->value],
            $prefs->getProductTourSeenPages(),
        );

        $prefs->syncEnabledProductTourPages(
            array_map(static fn (ProductTourPage $page): string => $page->value, ProductTourPage::all()),
        );
        self::assertSame([], $prefs->getProductTourSeenPages());
        self::assertSame(
            array_map(static fn (ProductTourPage $page): string => $page->value, ProductTourPage::all()),
            $prefs->getEnabledProductTourPages(),
        );

        $prefs->markProductTourSeen();
        self::assertTrue($prefs->isProductTourSeen());
        self::assertTrue($prefs->hasSeenTourPage('anything'));
        self::assertSame([], $prefs->getEnabledProductTourPages());
        self::assertSame(
            array_map(static fn (ProductTourPage $page): string => $page->value, ProductTourPage::all()),
            $prefs->getProductTourSeenPages(),
        );
    }
}
