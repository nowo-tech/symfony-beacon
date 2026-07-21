<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class AccountPreferencesTest extends DatabaseWebTestCase
{
    public function testUserCanChangePasswordAndCannotReusePrevious(): void
    {
        [$client, $user] = $this->bootWithDemoProject('pwd-policy@example.com', 'OldSecret1!Abc');
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/account/security');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.form-password-toggle');
        self::assertSelectorExists('.password-strength-input');
        self::assertSelectorExists('.password-strength-generate-btn');
        self::assertSelectorTextContains('.password-strength-generate-btn', 'Generate password');
        self::assertSelectorExists('[data-testid="password-change-history"]');
        self::assertSelectorTextContains('[data-testid="password-change-history"]', 'No password changes recorded yet');
        self::assertCount(3, $crawler->filter('.form-password-toggle'));

        $form = $crawler->selectButton('Update password')->form([
            'user_preferences[currentPassword]' => 'OldSecret1!Abc',
            'user_preferences[plainPassword]' => 'NewStrongPass1!',
            'user_preferences[plainPassword_confirm]' => 'NewStrongPass1!',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/account/security');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="password-change-entry"]');
        self::assertSelectorTextContains('[data-testid="password-change-history"]', 'Password changed');
        self::assertSelectorTextContains('[data-testid="password-change-history"]', 'Current password set on');
        $historyHtml = $client->getResponse()->getContent() ?: '';
        self::assertStringNotContainsString('$2y$', $historyHtml);
        self::assertStringNotContainsString('$argon', $historyHtml);

        $crawler = $client->request(Request::METHOD_GET, '/account/security');
        $form = $crawler->selectButton('Update password')->form([
            'user_preferences[currentPassword]' => 'NewStrongPass1!',
            'user_preferences[plainPassword]' => 'OldSecret1!Abc',
            'user_preferences[plainPassword_confirm]' => 'OldSecret1!Abc',
        ]);
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $html = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('old one', strtolower($html));
    }

    public function testAccountSecurityRejectsWeakNewPassword(): void
    {
        [$client, $user] = $this->bootWithDemoProject('pwd-weak@example.com', 'OldSecret1!Abc');
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/account/security');
        $form = $crawler->selectButton('Update password')->form([
            'user_preferences[currentPassword]' => 'OldSecret1!Abc',
            'user_preferences[plainPassword]' => 'Weak1!',
            'user_preferences[plainPassword_confirm]' => 'Weak1!',
        ]);
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $html = strtolower($client->getResponse()->getContent() ?: '');
        self::assertTrue(
            str_contains($html, 'strength') || str_contains($html, 'fortaleza') || str_contains($html, 'requirements'),
            'Expected password strength validation error in response',
        );
    }

    public function testPreferencesIndexRedirectsToProfile(): void
    {
        [$client, $user] = $this->bootWithDemoProject('prefs-redirect@example.com');
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account/preferences');
        self::assertResponseRedirects('/account/profile');
    }

    public function testPreferencesPageRequiresAuth(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/account/profile');
        self::assertResponseRedirects('/en/login');
    }

    public function testUserCanUpdateProfile(): void
    {
        [$client, $user] = $this->bootWithDemoProject('prefs@example.com');
        $user->setDisplayName('Pref User');
        self::getContainer()->get('doctrine')->getManager()->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/account/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="profile-overview"]');
        self::assertSelectorExists('[data-testid="profile-account-meta"]');
        self::assertSelectorExists('[data-testid="profile-projects"]');
        self::assertSelectorExists('[data-testid="profile-groups"]');
        self::assertSelectorTextContains('[data-testid="profile-overview"]', 'prefs@example.com');
        self::assertSelectorTextContains('[data-testid="profile-projects"]', 'Acme');

        $form = $crawler->selectButton('Save profile')->form([
            'user_preferences[displayName]' => 'Updated Prefs',
            'user_preferences[email]' => 'prefs@example.com',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/account/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('.user-menu__name', 'Updated Prefs');
        self::assertSelectorTextContains('[data-testid="profile-overview"]', 'Updated Prefs');
    }

    public function testUserCanUpdateDisplayPreferences(): void
    {
        [$client, $user] = $this->bootWithDemoProject('display@example.com');
        $user->setDisplayName('Display User');
        self::getContainer()->get('doctrine')->getManager()->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/account/display');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save display settings')->form();
        $values = $form->getPhpValues();
        $values['user_preferences']['preferredLocale'] = 'es';
        $values['user_preferences']['preferredTheme'] = 'dark';
        $values['user_preferences']['preferredContentWidth'] = 'full';
        $values['user_preferences']['preferredUiDensity'] = 'compact';
        $values['user_preferences']['preferredFontScale'] = 'lg';
        $values['user_preferences']['preferredContrast'] = 'more';
        $values['user_preferences']['preferredSidebar'] = 'collapsed';
        $values['user_preferences']['preferredMotion'] = 'reduce';
        $values['user_preferences']['preferredCollapsedIssuePanels'] = json_encode(['raw', 'tags'], \JSON_THROW_ON_ERROR);
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects('/account/display');
        $client->followRedirect();
        self::assertSelectorExists('[data-app-shell].is-full-width');
        self::assertSelectorExists('[data-app-shell][data-ui-density="compact"]');
        self::assertSelectorExists('[data-app-shell][data-font-scale="lg"]');
        self::assertSelectorExists('[data-app-shell][data-contrast="more"]');
        self::assertSelectorExists('[data-app-shell][data-sidebar-default="collapsed"]');
        $html = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('__BEACON_USER_THEME__', $html);
        self::assertStringContainsString('dark', $html);
        self::assertStringContainsString('__BEACON_USER_DENSITY__', $html);
        self::assertStringContainsString('compact', $html);
        self::assertStringContainsString('__BEACON_USER_MOTION__', $html);
        self::assertStringContainsString('reduce', $html);
        self::assertStringContainsString('__BEACON_USER_FONT_SCALE__', $html);
        self::assertStringContainsString('"lg"', $html);
        self::assertStringContainsString('__BEACON_USER_CONTRAST__', $html);
        self::assertStringContainsString('"more"', $html);
        self::assertStringContainsString('__BEACON_USER_SIDEBAR__', $html);
        self::assertStringContainsString('"collapsed"', $html);
        self::assertStringContainsString('__BEACON_ISSUE_PANEL_DEFAULTS__', $html);
        self::assertStringContainsString('"raw"', $html);
        self::assertStringContainsString('"tags"', $html);
        self::assertStringContainsString('nowo-tag-input', $html);
        self::assertStringContainsString('bundles/nowotaginput/tag-input.js', $html);

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertSame(['raw', 'tags'], $reloaded->getPreferredCollapsedIssuePanels());
        self::assertSame('lg', $reloaded->getPreferredFontScale());
        self::assertSame('more', $reloaded->getPreferredContrast());
        self::assertSame('collapsed', $reloaded->getPreferredSidebar());
    }

    public function testPreferencesSidebarHasSplitMenuItems(): void
    {
        [$client, $user] = $this->bootWithDemoProject('prefs-menu@example.com');
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account/profile');
        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('href="/account/profile"', $content);
        self::assertStringContainsString('href="/account/security"', $content);
        self::assertStringContainsString('href="/account/display"', $content);
        self::assertSelectorTextContains('#preferences-menu-navigation', 'Profile');
        self::assertSelectorTextContains('#preferences-menu-navigation', 'Security');
        self::assertSelectorTextContains('#preferences-menu-navigation', 'Display');
    }

    public function testDisplayPreferencesIncludesPwaInstallPanel(): void
    {
        [$client, $user] = $this->bootWithDemoProject('display-pwa@example.com');
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account/display');
        self::assertResponseIsSuccessful();
        $display = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('id="display-pwa-heading"', $display);
        self::assertStringContainsString('id="nowo-pwa-install-links"', $display);

        $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();
        $dashboard = $client->getResponse()->getContent() ?: '';
        self::assertStringNotContainsString('id="nowo-pwa-install-links"', $dashboard);
        self::assertStringContainsString('id="nowo-pwa-install"', $dashboard);
    }

    public function testAvatarMenuHasThreeSectionLinksForAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject('menu-sections@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setDisplayName('Section Admin');
        self::getContainer()->get('doctrine')->getManager()->flush();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('>Preferences<', $content);
        self::assertStringContainsString('>Dashboard<', $content);
        self::assertStringContainsString('>Administration<', $content);
        self::assertStringContainsString('/account/profile', $content);
        self::assertStringContainsString('href="/admin"', $content);
        self::assertStringNotContainsString('Account settings', $content);
        self::assertStringNotContainsString('Admin overview', $content);
    }

    public function testAdminHubRequiresAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject();
        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminHubAccessibleForAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject('hub-admin@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        self::getContainer()->get('doctrine')->getManager()->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Administration');
        self::assertSelectorExists('#administration-menu-navigation');
    }
}
