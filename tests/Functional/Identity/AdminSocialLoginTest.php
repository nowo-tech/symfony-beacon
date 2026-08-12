<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Entity\SocialLoginCredential;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;
use Symfony\Component\HttpFoundation\Request;

final class AdminSocialLoginTest extends DatabaseWebTestCase
{
    public function testSocialLoginSettingsRequireAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject('social-login-member@example.com');
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/admin/social-login');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanViewIndexAndCreateBuiltinProvider(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('social-login-admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/social-login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Social login');
        self::assertSelectorTextContains('body', 'No OAuth providers configured yet');
        self::assertSelectorExists('a[href*="provider=github"]');

        $crawler = $client->request(Request::METHOD_GET, '/admin/social-login/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save provider')->form([
            'social_login_credential[provider]' => 'custom-idp',
            'social_login_credential[label]' => 'Custom IdP',
            'social_login_credential[client_id]' => 'custom-client-id',
            'social_login_credential[client_secret]' => 'custom-client-secret',
            'social_login_credential[enabled]' => '1',
            'social_login_credential[authorize_url]' => 'https://idp.example/oauth/authorize',
            'social_login_credential[token_url]' => 'https://idp.example/oauth/token',
            'social_login_credential[userinfo_url]' => 'https://idp.example/oauth/userinfo',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/admin/social-login');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'custom-idp');

        $credential = self::getContainer()->get(SocialLoginCredentialRepository::class)->findOneByProvider('custom-idp');
        self::assertInstanceOf(SocialLoginCredential::class, $credential);
        self::assertSame('Custom IdP', $credential->getLabel());
        self::assertTrue($credential->isEnabled());
    }

    public function testAdminCanEditAndDeleteProvider(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('social-login-crud@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $credential = new SocialLoginCredential();
        $credential->setProvider('google');
        $credential->setLabel('Google');
        $credential->setClientId('google-id');
        $credential->setClientSecret('google-secret');
        $credential->setEnabled(false);
        $em->persist($credential);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/social-login/google/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save provider')->form();
        $form['social_login_credential[label]'] = 'Google Workspace';
        $form['social_login_credential[client_id]'] = 'google-id-updated';
        $form['social_login_credential[enabled]'] = '1';
        $client->submit($form);
        self::assertResponseRedirects('/admin/social-login');

        $em->clear();
        $updated = $em->getRepository(SocialLoginCredential::class)->findOneByProvider('google');
        self::assertInstanceOf(SocialLoginCredential::class, $updated);
        self::assertSame('Google Workspace', $updated->getLabel());
        self::assertTrue($updated->isEnabled());

        $crawler = $client->request(Request::METHOD_GET, '/admin/social-login');
        $client->submit($crawler->filter('form[action$="/admin/social-login/google/delete"]')->form());
        self::assertResponseRedirects('/admin/social-login');

        $em->clear();
        self::assertNull($em->getRepository(SocialLoginCredential::class)->findOneByProvider('google'));
    }

    public function testNewBuiltinProviderRedirectsToEditWhenAlreadyExists(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('social-login-redirect@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $credential = new SocialLoginCredential();
        $credential->setProvider('microsoft');
        $credential->setLabel('Microsoft');
        $credential->setClientId('ms-id');
        $credential->setClientSecret('ms-secret');
        $credential->setEnabled(true);
        $em->persist($credential);
        $em->flush();

        $this->login($client, $admin);
        $client->request(Request::METHOD_GET, '/admin/social-login/new', ['provider' => 'microsoft']);
        self::assertResponseRedirects('/admin/social-login/microsoft/edit');
    }
}
