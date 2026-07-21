<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Identity\Repository\UserRepository;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class SetupWizardTest extends DatabaseWebTestCase
{
    public function testAnonymousCanOpenBootstrapWhenNoUsers(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/setup');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Instance setup');
        self::assertSelectorExists('input[name="action"][value="minimum"]');
        self::assertSelectorExists('input[name="action"][value="bulk"]');
        self::assertSelectorNotExists('input[name="action"][value="platform"]');
    }

    public function testAnonymousMinimumBootstrapCreatesDemoAndRedirectsToLogin(): void
    {
        $client = static::createClient();

        $crawler = $client->request(Request::METHOD_GET, '/setup');
        $client->submit($crawler->filter('input[name="action"][value="minimum"]')->closest('form')->form());
        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('admin@symfony-beacon.local');
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());

        $reloaded = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        self::assertTrue($reloaded->isSetupCompleted());

        $client->request(Request::METHOD_GET, '/setup');
        self::assertResponseRedirects();
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
        self::assertResponseRedirects('/setup');
        $client->followRedirect();

        $reloaded = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        self::assertTrue($reloaded->isSetupCompleted());
    }
}
