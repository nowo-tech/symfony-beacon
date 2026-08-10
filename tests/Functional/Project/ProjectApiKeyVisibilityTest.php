<?php

declare(strict_types=1);

namespace App\Tests\Functional\Project;

use App\Identity\Entity\User;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectApiKeyVisibilityTest extends DatabaseWebTestCase
{
    public function testViewerCannotOpenSettingsAndDoesNotSeeSettingsNav(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('api-key-viewer-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $viewer = new User();
        $viewer->setEmail('api-key-viewer@example.com');
        $viewer->setDisplayName('Viewer');
        $viewer->setPassword($hasher->hashPassword($viewer, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($viewer);
        $membership->setRole(ProjectRole::Viewer);
        $project->addMembership($membership);
        $em->persist($viewer);
        $em->flush();

        /** @var ProjectApiKey $apiKey */
        $apiKey = $em->getRepository(ProjectApiKey::class)->findOneBy(['project' => $project]);
        self::assertInstanceOf(ProjectApiKey::class, $apiKey);
        $secret = (string) $apiKey->getSecretKey();
        self::assertNotSame('', $secret);

        $this->login($client, $viewer);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertStringNotContainsString($secret, (string) $client->getResponse()->getContent());

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-tour="project-settings"]');
        self::assertStringNotContainsString($secret, (string) $client->getResponse()->getContent());
    }

    public function testOwnerSeesApiKeyDsnOnlyAfterCreateOrRotate(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('api-key-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        /** @var ProjectApiKey $apiKey */
        $apiKey = $em->getRepository(ProjectApiKey::class)->findOneBy(['project' => $project]);
        self::assertInstanceOf(ProjectApiKey::class, $apiKey);
        $secret = (string) $apiKey->getSecretKey();

        $this->login($client, $owner);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="api-key-dsn-redacted"]');
        self::assertSelectorNotExists('[data-testid="api-key-dsn"]');
        self::assertStringNotContainsString($secret, (string) $client->getResponse()->getContent());
        self::assertSelectorExists('[data-tour="project-settings"]');

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        $form = $crawler->filter('form[action$="/keys"]')->form([
            'label' => 'show-once-key',
        ]);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="api-key-dsn"]');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('show-once-key', $html);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="api-key-dsn"]');
    }
}
