<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

final class MagicLoginTest extends DatabaseWebTestCase
{
    public function testMagicLoginHiddenWithoutEncryptedMailerDsn(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/login/magic');
        self::assertResponseRedirects('/en/login/magic');
        $client->followRedirect();
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/en/login/magic"]');
        self::assertSelectorNotExists('a[href="/en/reset-password"]');

        $mailer = self::getContainer()->get(ConfiguredMailer::class);
        self::assertFalse($mailer->isMagicLoginAvailable());
    }

    public function testPasswordResetHiddenWithoutEncryptedMailerDsn(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/reset-password');
        self::assertResponseRedirects('/en/reset-password');
        $client->followRedirect();
        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
    }

    public function testMagicLoginRequestAndConsume(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $this->enableEncryptedMailer();
        // Keep the same kernel so encrypted InstanceSettings stay readable across requests.
        $client->disableReboot();

        $user = new User();
        $user->setEmail('magic@example.com');
        $user->setDisplayName('Magic');
        $user->setPassword($hasher->hashPassword($user, 'secret'));
        $em->persist($user);
        $em->flush();

        $client->request(Request::METHOD_GET, '/login');
        self::assertResponseRedirects('/en/login');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/en/login/magic"]');
        self::assertSelectorExists('a[href="/en/reset-password"]');

        $crawler = $client->request(Request::METHOD_GET, '/en/login/magic');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form')->form();
        $phpValues = $form->getPhpValues();
        $root = (string) array_key_first($phpValues);
        self::assertNotSame('', $root);
        $phpValues[$root]['identifier'] = 'magic@example.com';
        $client->request(
            Request::METHOD_POST,
            '/en/login/magic',
            $phpValues,
        );
        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        /** @var LoginLinkHandlerInterface $handler */
        $handler = self::getContainer()->get('security.authenticator.login_link_handler.main');
        $details = $handler->createLoginLink($user);
        $client->request(Request::METHOD_GET, $details->getUrl());
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertNotNull($client->getContainer()->get('security.token_storage')->getToken()?->getUser());
    }

    public function testDisabledUserDoesNotAuthenticateViaMagicLink(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $this->enableEncryptedMailer();
        $client->disableReboot();

        $user = new User();
        $user->setEmail('magic-disabled@example.com');
        $user->setDisplayName('Disabled');
        $user->setPassword($hasher->hashPassword($user, 'secret'));
        $user->setEnabled(false);
        $em->persist($user);
        $em->flush();

        /** @var LoginLinkHandlerInterface $handler */
        $handler = self::getContainer()->get('security.authenticator.login_link_handler.main');
        $details = $handler->createLoginLink($user);
        $client->request(Request::METHOD_GET, $details->getUrl());
        $status = $client->getResponse()->getStatusCode();
        self::assertTrue($status >= 300 || $status < 200 || $status >= 400, 'Disabled magic login must not succeed with 2xx');
        $tokenUser = $client->getContainer()->get('security.token_storage')->getToken()?->getUser();
        self::assertFalse($tokenUser instanceof User && 'magic-disabled@example.com' === $tokenUser->getEmail());
    }

    public function testNullTransportDsnDoesNotEnableMagicLogin(): void
    {
        $client = self::createClient();
        $repo = self::getContainer()->get(InstanceSettingsRepository::class);
        $settings = $repo->getOrCreate();
        $settings->setMailerDsn('null://null');
        $repo->save($settings);

        self::assertFalse(self::getContainer()->get(ConfiguredMailer::class)->isMagicLoginAvailable());

        $client->request(Request::METHOD_GET, '/login/magic');
        self::assertResponseRedirects('/en/login/magic');
        $client->followRedirect();
        self::assertResponseRedirects();
    }

    private function enableEncryptedMailer(): void
    {
        $repo = self::getContainer()->get(InstanceSettingsRepository::class);
        $settings = $repo->getOrCreate();
        $settings->setMailerDsn('smtp://user:pass@127.0.0.1:1025');
        $settings->setMailerFrom('beacon@example.com');
        $repo->save($settings);
        self::getContainer()->get(ConfiguredMailer::class)->reset();
        self::assertTrue(self::getContainer()->get(ConfiguredMailer::class)->isMagicLoginAvailable());
    }
}
