<?php

declare(strict_types=1);

namespace App\Tests\Functional\Project;

use App\Project\Entity\Project;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Admin ingest suspend/resume on the Project-owned admin controller.
 */
final class AdminProjectIngestTest extends DatabaseWebTestCase
{
    public function testAdminCanSuspendIngestFromProjectAdmin(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('admin-ingest-toggle@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid());
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('form[action$="/ingest"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/admin/projects/'.$project->getUuid().'/ingest', [
            '_token' => $token,
            'enabled' => '0',
        ]);
        self::assertResponseRedirects('/admin/projects/'.$project->getUuid());

        $em->clear();
        $project = $em->getRepository(Project::class)->find($project->getId());
        self::assertFalse($project?->isIngestEnabled());
    }
}
