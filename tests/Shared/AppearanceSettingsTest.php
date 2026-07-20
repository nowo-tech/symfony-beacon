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

    public function testAdminCanUpdateBrandAndAccent(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-look@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/settings/appearance');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save appearance')->form([
            'site_appearance[brandName]' => 'Acme Beacon',
            'site_appearance[brandEyebrow]' => 'Ops monitoring',
            'site_appearance[accentColor]' => '#0d9488',
            'site_appearance[accentDeepColor]' => '#0f766e',
            'site_appearance[accentColorDark]' => '#2dd4bf',
            'site_appearance[accentDeepColorDark]' => '#5eead4',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/settings/appearance');
        $client->followRedirect();
        self::assertSelectorTextContains('a.brand-mark', 'Acme Beacon');
        self::assertSelectorExists('style');
        self::assertStringContainsString('--beacon-moss: #0d9488', $client->getResponse()->getContent() ?: '');
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

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/settings/appearance');
        $form = $crawler->selectButton('Save appearance')->form([
            'site_appearance[brandName]' => 'Custom Ops',
            'site_appearance[brandEyebrow]' => 'Signals',
            'site_appearance[accentColor]' => '#1f6f54',
            'site_appearance[accentDeepColor]' => '#134736',
            'site_appearance[accentColorDark]' => '#4aad7f',
            'site_appearance[accentDeepColorDark]' => '#6bc49a',
        ]);
        $client->submit($form);

        $client->request(Request::METHOD_GET, '/en/logout');
        $client->request(Request::METHOD_GET, '/en/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.brand-mark', 'Custom Ops');
        self::assertSelectorTextContains('body', 'Signals');
    }
}
