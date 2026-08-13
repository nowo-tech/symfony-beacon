<?php

declare(strict_types=1);

namespace App\Tests\Functional\Project;

use App\Project\Entity\ProjectApiKey;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Project settings API key rotate (owner surface).
 */
final class ProjectApiKeyRotateTest extends DatabaseWebTestCase
{
    public function testOwnerCanRotateApiKey(): void
    {
        [$client, $owner, $project, $apiKey] = $this->bootWithDemoProject('owner-key-rotate@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseIsSuccessful();

        $keyId = $apiKey->getId();
        self::assertNotNull($keyId);
        $rotateForm = $crawler->filter('form[action$="/keys/'.$keyId.'/rotate"]');
        self::assertGreaterThan(0, $rotateForm->count());
        $token = $rotateForm->filter('input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/keys/'.$keyId.'/rotate', [
            '_token' => $token,
        ]);
        self::assertResponseRedirects();

        $em->clear();
        $old = $em->getRepository(ProjectApiKey::class)->find($keyId);
        self::assertFalse($old?->isActive());
        $active = $em->getRepository(ProjectApiKey::class)->findOneBy([
            'project' => $project->getId(),
            'active' => true,
        ]);
        self::assertInstanceOf(ProjectApiKey::class, $active);
        self::assertNotSame($keyId, $active->getId());
    }
}
