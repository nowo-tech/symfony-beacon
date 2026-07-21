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
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/login/magic"]');

        $mailer = self::getContainer()->get(ConfiguredMailer::class);
        self::assertFalse($mailer->isMagicLoginAvailable());
    }

    public function testMagicLoginRequestAndConsume(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $this->enableEncryptedMailer();

        $user = new User();
        $user->setEmail('magic@example.com');
        $user->setDisplayName('Magic');
        $user->setPassword($hasher->hashPassword($user, 'secret'));
        $em->persist($user);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, '/en/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/login/magic"]');

        $crawler = $client->request(Request::METHOD_GET, '/login/magic');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form')->form([
            'email' => 'magic@example.com',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/login/magic');
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
