<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Tests\Support\DatabaseWebTestCase;
use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class MercureSettingsTest extends DatabaseWebTestCase
{
    public function testMercureSettingsRequireAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mercure-member@example.com');
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/settings/mercure');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanEnableMercureWithDatabaseSettings(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mercure-admin@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $client->disableReboot();
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/settings/mercure');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Mercure is off');

        $form = $crawler->selectButton('Save Mercure settings')->form([
            'instance_mercure_settings[mercureEnabled]' => '1',
            'instance_mercure_settings[mercureUrl]' => 'http://mercure/.well-known/mercure',
            'instance_mercure_settings[mercurePublicUrl]' => 'https://beacon.test/.well-known/mercure',
            'instance_mercure_settings[plainMercureJwtSecret]' => '!ChangeThisMercureHubJWTSecretKey!',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/settings/mercure');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Live Mercure alerts are enabled');

        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        self::assertTrue($settings->isMercureEnabled());
        self::assertSame('http://mercure/.well-known/mercure', $settings->getMercureUrl());
        self::assertTrue(self::getContainer()->get(ConfiguredMercure::class)->isEnabled());
    }

    public function testAdminCanClearStoredMercureJwtSecret(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mercure-clear@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);

        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        $settings->setMercureEnabled(true);
        $settings->setMercureUrl('http://mercure/.well-known/mercure');
        $settings->setMercureJwtSecret('stored-secret-at-least-32-chars-long');
        self::getContainer()->get(InstanceSettingsRepository::class)->save($settings);
        $em->flush();

        $client->disableReboot();
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/settings/mercure');
        $form = $crawler->selectButton('Save Mercure settings')->form([
            'instance_mercure_settings[clearMercureJwtSecret]' => '1',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/settings/mercure');

        $em->clear();
        $reloaded = $em->getRepository(InstanceSettings::class)->find($settings->getId());
        self::assertInstanceOf(InstanceSettings::class, $reloaded);
        self::assertNull($reloaded->getMercureJwtSecret());
    }
}
