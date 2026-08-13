<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Entity\User;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DashboardAccessTest extends DatabaseWebTestCase
{
    public function testLoginPageIsPublic(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/login');
        if ($client->getResponse()->isRedirection()) {
            self::assertResponseRedirects('/en/login');
            $client->followRedirect();
        }
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'symfony-beacon');
    }

    public function testDashboardRequiresAuth(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/dashboard');
        self::assertTrue($client->getResponse()->isRedirection());
        self::assertMatchesRegularExpression('#/(en/)?login$#', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testRootRedirectsToLogin(): void
    {
        $client = self::createClient();
        // Empty platform catalogs force /setup; seed so home can reach login.
        $this->seedPlatformCatalogs();
        $client->request(Request::METHOD_GET, '/');
        self::assertTrue($client->getResponse()->isRedirection());
        $location = (string) $client->getResponse()->headers->get('Location');
        if (str_contains($location, '/setup') || str_ends_with($location, '/setup')) {
            self::fail('Expected login redirect after platform seed, got: '.$location);
        }
        self::assertMatchesRegularExpression('#/(en/)?login$#', $location);
    }

    public function testOwnerSeesProject(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $project->getName());

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid());
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Issues');

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/general');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'API keys');
    }

    public function testForeignProjectIsDenied(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('owner2@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $stranger = new User();
        $stranger->setEmail('stranger@example.com');
        $stranger->setDisplayName('Stranger');
        $stranger->setPassword($hasher->hashPassword($stranger, 'secret'));
        $em->persist($stranger);
        $em->flush();

        $this->login($client, $stranger);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid());
        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateProjectFlow(): void
    {
        [$client, $user] = $this->bootWithDemoProject();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/projects/new');
        self::assertResponseRedirects('/dashboard?new=1');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog.confirm-dialog');

        $form = $crawler->selectButton('Create project')->form([
            'project[name]' => 'Billing API',
            'project[description]' => 'Tracks billing errors',
        ]);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Billing API');
        self::assertSelectorTextContains('body', 'API keys');
        self::assertSelectorTextContains('body', 'Settings');
    }
}
