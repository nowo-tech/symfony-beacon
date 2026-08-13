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
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertStringNotContainsString($secret, (string) $client->getResponse()->getContent());

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-nav="project-settings"]');
        self::assertStringNotContainsString($secret, (string) $client->getResponse()->getContent());
    }

    public function testOwnerDoesNotSeeSecretOnOrdinarySettingsGet(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('api-key-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        /** @var ProjectApiKey $apiKey */
        $apiKey = $em->getRepository(ProjectApiKey::class)->findOneBy(['project' => $project]);
        self::assertInstanceOf(ProjectApiKey::class, $apiKey);
        $secret = (string) $apiKey->getSecretKey();
        self::assertNotSame('', $secret);
        self::assertTrue($apiKey->isActive());

        $this->login($client, $owner);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="api-key-dsn"]');
        self::assertSelectorExists('[data-testid="api-key-dsn-redacted"]');
        self::assertStringNotContainsString($secret, (string) $client->getResponse()->getContent());

        $apiKey->setActive(false);
        $em->flush();

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="api-key-inactive"]');
        self::assertSelectorNotExists('[data-testid="api-key-dsn"]');
        self::assertStringNotContainsString($secret, (string) $client->getResponse()->getContent());
    }

    public function testOwnerSeesOneShotDsnBannerAfterRotate(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('api-key-rotate@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        /** @var ProjectApiKey $apiKey */
        $apiKey = $em->getRepository(ProjectApiKey::class)->findOneBy(['project' => $project, 'active' => true]);
        self::assertInstanceOf(ProjectApiKey::class, $apiKey);
        $oldSecret = (string) $apiKey->getSecretKey();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseIsSuccessful();
        $rotateAction = '/projects/'.$project->getUuid().'/keys/'.$apiKey->getId().'/rotate';
        $token = $crawler->filter('form[action$="'.$rotateAction.'"] input[name="_token"]')->attr('value');
        self::assertNotNull($token);
        $client->request(
            Request::METHOD_POST,
            $rotateAction,
            ['_token' => $token],
        );
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="api-key-dsn-flash"]');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString($oldSecret, $html);

        $em->clear();
        /** @var ProjectApiKey $rotated */
        $rotated = $em->getRepository(ProjectApiKey::class)->findOneBy(['project' => $project, 'active' => true]);
        self::assertInstanceOf(ProjectApiKey::class, $rotated);
        $newSecret = (string) $rotated->getSecretKey();
        self::assertNotSame('', $newSecret);
        self::assertNotSame($oldSecret, $newSecret);
        self::assertStringContainsString($newSecret, $html);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="api-key-dsn-flash"]');
        self::assertStringNotContainsString($newSecret, (string) $client->getResponse()->getContent());
    }
}
