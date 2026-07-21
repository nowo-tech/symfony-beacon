<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Identity\Repository\UserRepository;
use App\Shared\Breadcrumb\BreadcrumbDemoSeeder;
use App\Shared\CookieConsent\CookieConsentDemoSeeder;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\PlatformBootstrapState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class SetupWizardTest extends DatabaseWebTestCase
{
    public function testBareSetupServesDefaultLocale(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/setup');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Instance setup');
    }

    public function testDefaultLocalePrefixedSetupRedirectsToBare(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/en/setup');
        self::assertResponseRedirects('/setup');
    }

    public function testHomeRedirectsToSetupWhenPlatformEmpty(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/');
        self::assertResponseRedirects('/setup');
    }

    public function testLoginAndHealthDoNotRedirectWhenPlatformEmpty(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/en/login');
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/health/live');
        self::assertResponseIsSuccessful();
    }

    public function testAnonymousCanOpenBootstrapWhenNoUsers(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/setup');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Instance setup');
        self::assertSelectorExists('input[name="action"][value="platform"]');
        self::assertSelectorNotExists('input[name="action"][value="minimum"]');
        self::assertSelectorNotExists('input[name="action"][value="bulk"]');
        self::assertSelectorNotExists('a[href="/en/register"]');
        self::assertSelectorExists('[data-theme-toggle]');
        self::assertSelectorExists('.locale-switcher');
    }

    public function testSetupLocaleInPathSwitchesLanguage(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/es/setup');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Configuración de la instancia');
        self::assertSelectorExists('a[href="/setup"]');
    }

    public function testAnonymousPlatformInstallUnlocksRegisterLink(): void
    {
        $client = static::createClient();

        $crawler = $client->request(Request::METHOD_GET, '/setup');
        $client->submit($crawler->filter('input[name="action"][value="platform"]')->closest('form')->form());
        self::assertResponseRedirects('/setup');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertFalse(self::getContainer()->get(PlatformBootstrapState::class)->needsPlatformSeed());
        self::assertSelectorExists('a[href="/en/register"]');
        self::assertSelectorExists('input[name="action"][value="sample_load"]');
        self::assertSelectorExists('input[name="action"][value="complete"]');

        self::assertSame(0, self::getContainer()->get(UserRepository::class)->count([]));
    }

    public function testAnonymousCompleteRedirectsToLogin(): void
    {
        $client = static::createClient();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        self::getContainer()->get(BreadcrumbDemoSeeder::class)->seedIfEmpty();
        self::getContainer()->get(CookieConsentDemoSeeder::class)->seedIfEmpty();

        $crawler = $client->request(Request::METHOD_GET, '/setup');
        $client->submit($crawler->filter('input[name="action"][value="complete"]')->closest('form')->form());
        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);

        $reloaded = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        self::assertTrue($reloaded->isSetupCompleted());
    }

    public function testLoginShowsSetupLinkWhenBootstrapOpen(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/en/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/setup"]');
    }

    public function testSetupRequiresAdminWhenUsersExist(): void
    {
        [$client, $user] = $this->bootWithDemoProject('member@example.com');
        $user->setRoles(['ROLE_USER']);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/setup');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanRunPlatformAndComplete(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-setup@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->login($client, $user);

        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        $settings->clearSetupCompleted();
        self::getContainer()->get(InstanceSettingsRepository::class)->save($settings);

        $crawler = $client->request(Request::METHOD_GET, '/setup');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Instance setup');
        self::assertSelectorExists('input[name="action"][value="platform"]');

        $client->submit($crawler->filter('input[name="action"][value="platform"]')->closest('form')->form());
        self::assertResponseRedirects('/setup');
        $client->followRedirect();
        self::assertSelectorExists('.flash-success, .flash');

        $client->submit($client->getCrawler()->filter('input[name="action"][value="complete"]')->closest('form')->form());
        self::assertResponseRedirects('/dashboard');

        $reloaded = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        self::assertTrue($reloaded->isSetupCompleted());
    }
}
