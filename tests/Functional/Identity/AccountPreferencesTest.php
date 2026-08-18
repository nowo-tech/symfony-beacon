<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\UserActionType;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Nowo\AuthKitBundle\Entity\SocialLoginAccount;
use Nowo\AuthKitBundle\Entity\SocialLoginCredential;
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
        self::assertSelectorExists('.preferences-nav');
        self::assertSelectorTextContains('.preferences-nav', 'Change password');
        self::assertSelectorTextContains('.preferences-nav', 'Change history');
        self::assertSelectorNotExists('[data-testid="password-change-history"]');
        self::assertCount(3, $crawler->filter('.form-password-toggle'));

        $client->request(Request::METHOD_GET, '/account/security/history');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="password-change-history"]');
        self::assertSelectorTextContains('[data-testid="password-change-history"]', 'No password changes recorded yet');

        $crawler = $client->request(Request::METHOD_GET, '/account/security');
        $form = $crawler->selectButton('Update password')->form([
            'user_preferences[currentPassword]' => 'OldSecret1!Abc',
            'user_preferences[plainPassword]' => 'NewStrongPass1!',
            'user_preferences[plainPassword_confirm]' => 'NewStrongPass1!',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/account/security');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/account/security/history');
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

        $client->request(Request::METHOD_GET, '/account');
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
        self::assertSelectorExists('.preferences-nav');
        self::assertSelectorTextContains('.preferences-nav', 'My projects');
        self::assertSelectorTextContains('.preferences-nav', 'My groups');
        self::assertSelectorNotExists('[data-testid="profile-projects"]');
        self::assertSelectorNotExists('[data-testid="profile-groups"]');
        self::assertSelectorTextContains('[data-testid="profile-overview"]', 'prefs@example.com');

        $client->request(Request::METHOD_GET, '/account/projects');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="profile-projects"]');
        self::assertSelectorTextContains('[data-testid="profile-projects"]', 'Acme');

        $client->request(Request::METHOD_GET, '/account/groups');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="profile-groups"]');

        $crawler = $client->request(Request::METHOD_GET, '/account/profile');
        $form = $crawler->selectButton('Save profile')->form([
            'user_profile[displayName]' => 'Updated Prefs',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/account/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('.user-menu__name', 'Updated Prefs');
        self::assertSelectorTextContains('[data-testid="profile-overview"]', 'Updated Prefs');
    }

    public function testEmailChangeRequiresCurrentPassword(): void
    {
        [$client, $user] = $this->bootWithDemoProject('prefs-email@example.com', 'OldSecret1!Abc');
        $user->setDisplayName('Email Prefs');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/account/profile');
        $form = $crawler->selectButton('Save email & Slack ID')->form([
            'user_profile_sensitive[email]' => 'prefs-email-new@example.com',
            'user_profile_sensitive[currentPassword]' => '',
        ]);
        $client->submit($form);
        self::assertFalse($client->getResponse()->isRedirect());
        $html = strtolower($client->getResponse()->getContent() ?: '');
        self::assertStringContainsString('current password', $html);

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame('prefs-email@example.com', $reloaded->getEmail());

        $crawler = $client->request(Request::METHOD_GET, '/account/profile');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save email & Slack ID')->form();
        $form['user_profile_sensitive[email]'] = 'prefs-email-new@example.com';
        $form['user_profile_sensitive[currentPassword]'] = 'OldSecret1!Abc';
        $client->submit($form);
        self::assertResponseRedirects('/account/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('[data-testid="profile-overview"]', 'prefs-email-new@example.com');
    }

    public function testSavingPhoneDoesNotAutoVerifyIt(): void
    {
        [$client, $user] = $this->bootWithDemoProject('qr-phone@example.com');
        $user->setDisplayName('QR User');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/account/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="user_profile[phone][country_iso]"]');
        self::assertSelectorExists('input[name="user_profile[phone][national_number]"]');

        $form = $crawler->selectButton('Save profile')->form([
            'user_profile[displayName]' => 'QR User',
            'user_profile[phone][country_iso]' => 'ES',
            'user_profile[phone][national_number]' => '600111222',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/account/profile');

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame('+34600111222', $reloaded->getPhone());
        self::assertNull($reloaded->getPhoneVerifiedAt());
    }

    public function testSavingUnchangedPhoneKeepsExistingVerification(): void
    {
        [$client, $user] = $this->bootWithDemoProject('qr-phone-keep@example.com');
        $verifiedAt = new DateTimeImmutable('-1 day');
        $user->setDisplayName('QR Keep');
        $user->setPhone('+34600111222');
        $user->setPhoneVerifiedAt($verifiedAt);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/account/profile');
        $form = $crawler->selectButton('Save profile')->form([
            'user_profile[displayName]' => 'QR Keep',
            'user_profile[phone][country_iso]' => 'ES',
            'user_profile[phone][national_number]' => '600111222',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/account/profile');

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame($verifiedAt->format('Y-m-d H:i:s'), $reloaded->getPhoneVerifiedAt()?->format('Y-m-d H:i:s'));
    }

    public function testChangingPhoneClearsExistingVerification(): void
    {
        [$client, $user] = $this->bootWithDemoProject('qr-phone-clear@example.com');
        $user->setDisplayName('QR Clear');
        $user->setPhone('+34600111222');
        $user->setPhoneVerifiedAt(new DateTimeImmutable('-1 day'));
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/account/profile');
        $form = $crawler->selectButton('Save profile')->form([
            'user_profile[displayName]' => 'QR Clear',
            'user_profile[phone][country_iso]' => 'ES',
            'user_profile[phone][national_number]' => '600999999',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/account/profile');

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame('+34600999999', $reloaded->getPhone());
        self::assertNull($reloaded->getPhoneVerifiedAt());
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
        self::assertSelectorExists('.preferences-nav');
        self::assertSelectorTextContains('.preferences-nav', 'Appearance');
        self::assertSelectorTextContains('.preferences-nav', 'Issue panels');
        self::assertSelectorTextContains('.preferences-nav', 'Tours');
        self::assertSelectorTextContains('.preferences-nav', 'Notifications');
        self::assertSelectorNotExists('#display-pwa-heading');
        self::assertSelectorNotExists('#display-tour-heading');

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
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects('/account/display');
        $client->followRedirect();
        self::assertSelectorExists('[data-app-shell].is-full-width');
        self::assertSelectorExists('[data-app-shell][data-ui-density="compact"]');
        self::assertSelectorExists('[data-app-shell][data-font-scale="lg"]');
        self::assertSelectorExists('[data-app-shell][data-contrast="more"]');
        self::assertSelectorExists('[data-app-shell][data-sidebar-default="collapsed"]');
        $html = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('data-user-theme="dark"', $html);
        self::assertStringContainsString('data-theme="dark"', $html);
        self::assertStringContainsString('data-user-density="compact"', $html);
        self::assertStringContainsString('data-user-motion="reduce"', $html);
        self::assertStringContainsString('data-user-font-scale="lg"', $html);
        self::assertStringContainsString('data-user-contrast="more"', $html);
        self::assertStringContainsString('data-user-sidebar="collapsed"', $html);
        self::assertStringContainsString('build/theme-boot.js', $html);

        $crawler = $client->request(Request::METHOD_GET, '/account/display/panels');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="user_preferences"]')->form();
        $values = $form->getPhpValues();
        $values['user_preferences']['preferredCollapsedIssuePanels'] = json_encode(['raw', 'tags'], \JSON_THROW_ON_ERROR);
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects('/account/display/panels');
        $client->followRedirect();
        $html = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('data-issue-panel-defaults=', $html);
        self::assertMatchesRegularExpression('/data-issue-panel-defaults="[^"]*raw[^"]*tags[^"]*"/', $html);
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

        $client->request(Request::METHOD_GET, '/account/display/notifications');
        self::assertResponseIsSuccessful();
        $display = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('id="display-pwa-heading"', $display);
        self::assertStringContainsString('id="nowo-pwa-install-links"', $display);
        self::assertStringContainsString('nowo-pwa-install-links__install', $display);
        self::assertStringContainsString('data-pwa-install-action="install"', $display);

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

    public function testAccountAreaNavOnSecurityAndDisplay(): void
    {
        [$client, $user] = $this->bootWithDemoProject('account-area-nav@example.com');
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account/security');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="account-area-nav"]');
        self::assertSelectorTextContains('[data-testid="account-area-nav"]', 'Profile');
        self::assertSelectorTextContains('[data-testid="account-area-nav"]', 'Security');
        self::assertSelectorTextContains('[data-testid="account-area-nav"]', 'Display');
        self::assertSelectorExists('[data-testid="linked-social-accounts"]');
        self::assertSelectorTextContains('.preferences-nav', 'Activity');

        $client->request(Request::METHOD_GET, '/account/display');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="account-area-nav"] a[aria-current="page"]');
        self::assertSelectorTextContains('[data-testid="account-area-nav"] a[aria-current="page"]', 'Display');
    }

    public function testSecurityShowsLinkedSocialAndActivityScopedToUser(): void
    {
        [$client, $user] = $this->bootWithDemoProject('account-social-activity@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();

        $credential = new SocialLoginCredential();
        $credential->setProvider('github');
        $credential->setLabel('GitHub');
        $credential->setClientId('test-client');
        $credential->setClientSecret('test-secret');
        $credential->setEnabled(true);
        $em->persist($credential);

        $linked = new SocialLoginAccount();
        $linked->setProvider('github');
        $linked->setProviderUserId('gh-123');
        $linked->setUserClass(User::class);
        $linked->setUserId((string) $user->getId());
        $linked->setUserIdentifier($user->getEmail());
        $linked->setEmail($user->getEmail());
        $linked->setDisplayName('GH User');
        $em->persist($linked);

        $ownAction = new UserAction();
        $ownAction->setAction(UserActionType::PasswordResetRequested);
        $ownAction->setSubjectUser($user);
        $ownAction->setContext(['email' => $user->getEmail(), 'masked' => 'a***@example.com']);
        $em->persist($ownAction);

        $other = new User();
        $other->setEmail('other-security-activity@example.com');
        $other->setDisplayName('Other');
        $other->setPassword($user->getPassword());
        $em->persist($other);
        $em->flush();

        $otherAction = new UserAction();
        $otherAction->setAction(UserActionType::PasswordResetRequested);
        $otherAction->setSubjectUser($other);
        $otherAction->setContext(['email' => $other->getEmail(), 'masked' => 'o***@example.com']);
        $em->persist($otherAction);
        $em->flush();

        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account/security');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="linked-social-account"]');
        self::assertSelectorTextContains('[data-testid="linked-social-accounts"]', 'Github');
        self::assertSelectorTextContains('[data-testid="linked-social-accounts"]', 'Unlinking providers');

        $client->request(Request::METHOD_GET, '/account/security/activity');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="security-activity"]');
        self::assertSelectorTextContains('[data-testid="security-activity"]', 'Password reset requested');
        self::assertSelectorTextNotContains('[data-testid="security-activity"]', 'other-security-activity@example.com');
        self::assertSelectorExists('[data-testid="security-activity-entry"]');
    }
}
