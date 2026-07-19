<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DashboardAccessTest extends DatabaseWebTestCase
{
    public function testLoginPageIsPublic(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'symfony-beacon');
    }

    public function testDashboardRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/');
        self::assertResponseRedirects('/login');
    }

    public function testOwnerSeesProject(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $project->getName());

        $client->request(Request::METHOD_GET, '/projects/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'API keys');
    }

    public function testForeignProjectIsDenied(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('owner2@example.com');
        $em = static::getContainer()->get('doctrine')->getManager();
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $stranger = new User();
        $stranger->setEmail('stranger@example.com');
        $stranger->setDisplayName('Stranger');
        $stranger->setPassword($hasher->hashPassword($stranger, 'secret'));
        $em->persist($stranger);
        $em->flush();

        $this->login($client, $stranger);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateProjectFlow(): void
    {
        [$client, $user] = $this->bootWithDemoProject();
        $this->login($client, $user);

        $client->request(Request::METHOD_POST, '/projects/new', [
            'name' => 'Billing API',
            'description' => 'Tracks billing errors',
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Billing API');
    }
}
