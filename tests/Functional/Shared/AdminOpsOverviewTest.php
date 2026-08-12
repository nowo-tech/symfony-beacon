<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Analytics\Entity\DailyProjectStat;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Ops\Service\OpsOverviewService;
use App\Project\Entity\Project;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class AdminOpsOverviewTest extends DatabaseWebTestCase
{
    public function testSpikeRuleIsDeterministic(): void
    {
        self::assertFalse(OpsOverviewService::isSpike(3, 1.0));
        self::assertTrue(OpsOverviewService::isSpike(4, 1.0));
        self::assertTrue(OpsOverviewService::isSpike(10, 2.0));
        self::assertFalse(OpsOverviewService::isSpike(5, 3.0));
    }

    public function testNonAdminForbidden(): void
    {
        [$client, $user] = $this->bootWithDemoProject('ops-overview-member@example.com');
        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/admin/ops');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminSeesOverviewAndFilterScopes(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('ops-overview-admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $other = new Project();
        $other->setName('Other Ops Project');
        $other->setSlug('other-ops-project');
        $em->persist($other);

        $today = new DateTimeImmutable('now', new DateTimeZone('UTC'))->setTime(0, 0);
        $stat = new DailyProjectStat();
        $stat->setProject($project);
        $stat->setStatDate($today);
        $stat->incrementErrorCount(20);
        $em->persist($stat);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops webhook');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hooks/ops');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);
        $destination->recordDeliveryFailure('connection timed out');
        $em->persist($destination);

        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $client->request(Request::METHOD_GET, '/admin/ops');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ops overview');
        self::assertSelectorTextContains('body', $project->getName());
        self::assertSelectorTextContains('body', 'Ops webhook');
        self::assertSelectorTextContains('body', 'connection timed out');
        self::assertStringNotContainsString('https://example.test/hooks/ops', (string) $client->getResponse()->getContent());

        $client->request(Request::METHOD_GET, '/admin/ops', ['project' => $other->getUuid()]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No destinations with a failed last delivery');
        self::assertSelectorTextContains('body', 'No error spikes right now');
    }
}
