<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

final class MagicLoginTest extends DatabaseWebTestCase
{
    public function testMagicLoginRequestAndConsume(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('magic@example.com');
        $user->setDisplayName('Magic');
        $user->setPassword($hasher->hashPassword($user, 'secret'));
        $em->persist($user);
        $em->flush();

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
}
