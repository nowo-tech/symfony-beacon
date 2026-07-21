<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class SetupWizardTest extends DatabaseWebTestCase
{
    public function testSetupRequiresAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject('member@example.com');
        $user->setRoles(['ROLE_USER']);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/setup');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanRunPlatformAndComplete(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-setup@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->login($client, $user);

        $settings = static::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        $settings->clearSetupCompleted();
        static::getContainer()->get(InstanceSettingsRepository::class)->save($settings);

        $crawler = $client->request(Request::METHOD_GET, '/setup');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Instance setup');

        $client->submit($crawler->filter('input[name="action"][value="platform"]')->closest('form')->form());
        self::assertResponseRedirects('/setup');
        $client->followRedirect();
        self::assertSelectorExists('.flash-success, .flash');

        $client->submit($client->getCrawler()->filter('input[name="action"][value="complete"]')->closest('form')->form());
        self::assertResponseRedirects('/setup');
        $client->followRedirect();

        $reloaded = static::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        self::assertTrue($reloaded->isSetupCompleted());
    }
}
