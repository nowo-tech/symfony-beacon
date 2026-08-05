<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Identity\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppearanceSettingsTest extends DatabaseWebTestCase
{
    public function testAppearanceRequiresAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/settings/appearance');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAppearanceIndexRedirectsToThemes(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-appear-index@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/settings/appearance');
        self::assertResponseRedirects('/settings/appearance/themes');
    }

    public function testAdminCanApplyNamedTheme(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-theme@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/settings/appearance/themes');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="appearance-tabs"]');
        self::assertSelectorNotExists('[data-testid="appearance-subtabs"]');
        self::assertSelectorExists('button[name="apply_theme"][value="ocean"]');
        self::assertSelectorExists('button[name="apply_theme"][value="midnight"]');

        $form = $crawler->filter('button[name="apply_theme"][value="ocean"]')->form();
        $client->submit($form);
        self::assertResponseRedirects('/settings/appearance/themes');
        $client->followRedirect();

        $html = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('--beacon-moss: #0e7490', $html);
        self::assertStringContainsString('--beacon-paper: #f0f9fb', $html);
        self::assertSelectorExists('button[name="apply_theme"][value="ocean"][aria-pressed="true"]');

        $form = $client->getCrawler()->filter('button[name="apply_theme"][value="midnight"]')->form();
        $client->submit($form);
        self::assertResponseRedirects('/settings/appearance/themes');
        $client->followRedirect();
        self::assertSelectorExists('button[name="apply_theme"][value="midnight"][aria-pressed="true"]');
        self::assertSelectorExists('button[name="apply_theme"][value="ocean"][aria-pressed="true"]');
    }

    public function testAdminCanUpdateBrandAndAccent(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-look@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/settings/appearance/brand');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save appearance')->form([
            'site_appearance[brandName]' => 'Acme Beacon',
            'site_appearance[brandEyebrow]' => 'Ops monitoring',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/settings/appearance/brand');
        $client->followRedirect();
        self::assertSelectorTextContains('a.brand-mark', 'Acme Beacon');

        $crawler = $client->request(Request::METHOD_GET, '/settings/appearance/colors/accents');
        $form = $crawler->selectButton('Save appearance')->form([
            'site_appearance[accentColor]' => '#0d9488',
            'site_appearance[accentDeepColor]' => '#0f766e',
            'site_appearance[accentColorDark]' => '#2dd4bf',
            'site_appearance[accentDeepColorDark]' => '#5eead4',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/settings/appearance/colors/accents');
        $client->followRedirect();

        $crawler = $client->request(Request::METHOD_GET, '/settings/appearance/colors/status');
        $form = $crawler->selectButton('Save appearance')->form([
            'site_appearance[dangerColor]' => '#c2410c',
            'site_appearance[dangerColorDark]' => '#fb923c',
            'site_appearance[warnColor]' => '#a16207',
            'site_appearance[warnColorDark]' => '#facc15',
        ]);
        $client->submit($form);

        $crawler = $client->request(Request::METHOD_GET, '/settings/appearance/colors/surfaces');
        $form = $crawler->selectButton('Save appearance')->form([
            'site_appearance[paperColor]' => '#f0fdfa',
            'site_appearance[paperColorDark]' => '#042f2e',
            'site_appearance[inkColor]' => '#134e4a',
            'site_appearance[inkColorDark]' => '#ccfbf1',
            'site_appearance[surfaceColor]' => '#ffffff',
            'site_appearance[surfaceColorDark]' => '#115e59',
        ]);
        $client->submit($form);
        $client->followRedirect();

        self::assertSelectorExists('style');
        $html = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('--beacon-moss: #0d9488', $html);
        self::assertStringContainsString('--beacon-alert: #c2410c', $html);
        self::assertStringContainsString('--beacon-warn: #a16207', $html);
        self::assertStringContainsString('--beacon-paper: #f0fdfa', $html);
        self::assertStringContainsString('--beacon-ink: #134e4a', $html);
        self::assertStringContainsString('--beacon-surface: #ffffff', $html);
    }

    public function testAdminCanToggleFixedFooter(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-footer@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/settings/appearance/layout');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('html[data-footer-fixed="1"]');

        $form = $crawler->selectButton('Save appearance')->form([
            'site_appearance[footerFixed]' => '1',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/settings/appearance/layout');
        $client->followRedirect();
        self::assertSelectorExists('html[data-footer-fixed="1"]');
    }

    public function testAdminCanChangeCornerStyle(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-corners@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/settings/appearance/layout');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save appearance')->form([
            'site_appearance[cornerStyle]' => 'rounded',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/settings/appearance/layout');
        $client->followRedirect();

        $html = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('--beacon-radius-card: 1.25rem', $html);
        self::assertStringContainsString('--beacon-radius-control: 0.5rem', $html);
    }

    public function testAdminCanChangeBorderStrength(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-borders@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/settings/appearance/layout');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save appearance')->form([
            'site_appearance[borderStrength]' => 'strong',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/settings/appearance/layout');
        $client->followRedirect();

        $html = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('--beacon-border-width: 1.5px', $html);
        self::assertStringContainsString('26%', $html);
        self::assertStringContainsString('30%', $html);
    }

    public function testUserCanPersistThemeToggleChoice(): void
    {
        [$client, $user] = $this->bootWithDemoProject('theme-sync@example.com');
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();
        $shell = $crawler->filter('[data-app-shell][data-theme-sync-token]');
        self::assertGreaterThan(0, $shell->count());
        $token = (string) $shell->attr('data-theme-sync-token');
        self::assertNotSame('', $token);

        $client->request(
            Request::METHOD_POST,
            '/account/theme',
            server: [
                'HTTP_X_CSRF_TOKEN' => $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['theme' => 'dark'], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertIsArray($payload);
        self::assertTrue($payload['ok'] ?? false);

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertSame('dark', $reloaded->getPreferredTheme());
    }

    public function testUserCanPersistContentWidthToggleChoice(): void
    {
        [$client, $user] = $this->bootWithDemoProject('width-sync@example.com');
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-content-width-toggle]');
        $shell = $crawler->filter('[data-app-shell][data-content-width-sync-token]');
        self::assertGreaterThan(0, $shell->count());
        $token = (string) $shell->attr('data-content-width-sync-token');
        self::assertNotSame('', $token);

        $client->request(
            Request::METHOD_POST,
            '/account/content-width',
            server: [
                'HTTP_X_CSRF_TOKEN' => $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['contentWidth' => 'full'], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertIsArray($payload);
        self::assertTrue($payload['ok'] ?? false);
        self::assertSame('full', $payload['contentWidth'] ?? null);

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertSame('full', $reloaded->getPreferredContentWidth());
    }

    public function testLoginShowsCustomBrand(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = new User();
        $admin->setEmail('brand-admin@example.com');
        $admin->setDisplayName('Brand Admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'secret'));
        $em->persist($admin);
        $em->flush();

        $this->seedPlatformCatalogs();
        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/settings/appearance/brand');
        $form = $crawler->selectButton('Save appearance')->form([
            'site_appearance[brandName]' => 'Custom Ops',
            'site_appearance[brandEyebrow]' => 'Signals',
        ]);
        $client->submit($form);

        $csrf = $client->getContainer()->get('security.csrf.token_manager')->getToken('logout')->getValue();
        $client->request(Request::METHOD_GET, '/en/logout', [
            '_csrf_token' => $csrf,
        ]);
        $client->request(Request::METHOD_GET, '/en/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.brand-mark', 'Custom Ops');
        self::assertSelectorTextContains('body', 'Signals');
    }
}
