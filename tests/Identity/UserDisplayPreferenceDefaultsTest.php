<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Identity\UserDisplayPreferenceDefaults;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class UserDisplayPreferenceDefaultsTest extends DatabaseWebTestCase
{
    public function testNewUserPersistsConcreteDisplayDefaults(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $defaultLocale = (string) self::getContainer()->getParameter('default_locale');

        $user = new User();
        $user->setEmail('prefs-default-'.bin2hex(random_bytes(4)).'@example.test');
        $user->setDisplayName('Prefs Default');
        $user->setPassword('unused-hash');

        self::assertNull($user->getPreferredLocaleRaw());
        self::assertSame(UserDisplayPreferenceDefaults::THEME, $user->getPreferredThemeRaw());
        self::assertSame(UserDisplayPreferenceDefaults::MOTION, $user->getPreferredMotionRaw());
        self::assertSame(UserDisplayPreferenceDefaults::CONTRAST, $user->getPreferredContrastRaw());

        $em->persist($user);
        $em->flush();
        $em->clear();

        $reloaded = $em->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame($defaultLocale, $reloaded->getPreferredLocaleRaw());
        self::assertSame(UserDisplayPreferenceDefaults::THEME, $reloaded->getPreferredThemeRaw());
        self::assertSame(UserDisplayPreferenceDefaults::MOTION, $reloaded->getPreferredMotionRaw());
        self::assertSame(UserDisplayPreferenceDefaults::CONTRAST, $reloaded->getPreferredContrastRaw());
        self::assertSame(UserDisplayPreferenceDefaults::CONTENT_WIDTH, $reloaded->getPreferredContentWidthRaw());
        self::assertSame(UserDisplayPreferenceDefaults::UI_DENSITY, $reloaded->getPreferredUiDensityRaw());
        self::assertSame(UserDisplayPreferenceDefaults::FONT_SCALE, $reloaded->getPreferredFontScaleRaw());
        self::assertSame(UserDisplayPreferenceDefaults::SIDEBAR, $reloaded->getPreferredSidebarRaw());
    }

    public function testAccountDisplayFormSelectsDefaultsWhenLegacyColumnsAreNull(): void
    {
        [$client, $user] = $this->bootWithDemoProject('prefs-legacy-'.bin2hex(random_bytes(4)).'@example.test');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $userId = $user->getId();
        self::assertNotNull($userId);

        // Bypass PreUpdate healing so the GET path must heal legacy nulls.
        $em->getConnection()->executeStatement(
            'UPDATE app_user SET preferred_locale = NULL, preferred_theme = NULL, preferred_motion = NULL, preferred_contrast = NULL WHERE id = ?',
            [$userId],
        );
        $em->clear();

        $user = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $user);
        self::assertNull($user->getPreferredLocaleRaw());
        self::assertNull($user->getPreferredThemeRaw());

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/account/display');
        self::assertResponseIsSuccessful();

        $defaultLocale = (string) self::getContainer()->getParameter('default_locale');
        self::assertSame(
            $defaultLocale,
            $crawler->filter('#user_preferences_preferredLocale option[selected]')->attr('value'),
        );
        self::assertSame(
            UserDisplayPreferenceDefaults::THEME,
            $crawler->filter('#user_preferences_preferredTheme option[selected]')->attr('value'),
        );
        self::assertSame(
            UserDisplayPreferenceDefaults::CONTRAST,
            $crawler->filter('#user_preferences_preferredContrast option[selected]')->attr('value'),
        );
        self::assertSame(
            UserDisplayPreferenceDefaults::MOTION,
            $crawler->filter('#user_preferences_preferredMotion option[selected]')->attr('value'),
        );

        $em->clear();
        $healed = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $healed);
        self::assertSame($defaultLocale, $healed->getPreferredLocaleRaw());
        self::assertSame(UserDisplayPreferenceDefaults::THEME, $healed->getPreferredThemeRaw());
        self::assertSame(UserDisplayPreferenceDefaults::MOTION, $healed->getPreferredMotionRaw());
        self::assertSame(UserDisplayPreferenceDefaults::CONTRAST, $healed->getPreferredContrastRaw());
    }
}
