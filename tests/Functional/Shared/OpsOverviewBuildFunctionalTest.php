<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Analytics\Entity\DailyProjectStat;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Ops\Service\OpsOverviewService;
use App\Project\Entity\Project;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Integration coverage for {@see OpsOverviewService::build()} and the admin ops route.
 */
final class OpsOverviewBuildFunctionalTest extends DatabaseWebTestCase
{
    public function testBuildAggregatesOpenIssuesSpikesAndFailedDeliveries(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('ops-build-admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('ops-build-fp');
        $issue->setTitle('Ops open issue');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $em->persist($issue);

        $suspended = new Project();
        $suspended->setName('Suspended ingest');
        $suspended->setSlug('suspended-ingest-ops');
        $suspended->setIngestEnabled(false);
        $em->persist($suspended);

        $today = new DateTimeImmutable('now', new DateTimeZone('UTC'))->setTime(0, 0);
        $stat = new DailyProjectStat();
        $stat->setProject($project);
        $stat->setStatDate($today);
        $stat->incrementErrorCount(12);
        $em->persist($stat);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops build webhook');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hooks/ops-build');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);
        $destination->recordDeliveryFailure('timeout from test');
        $em->persist($destination);
        $em->flush();

        /** @var OpsOverviewService $service */
        $service = self::getContainer()->get(OpsOverviewService::class);
        $overview = $service->build();

        self::assertGreaterThanOrEqual(1, $overview['open_issues_total']);
        self::assertNotEmpty($overview['open_issues_by_project']);
        self::assertGreaterThanOrEqual(1, $overview['suspended_count']);
        self::assertNotEmpty($overview['spikes']);
        self::assertNotEmpty($overview['failed_deliveries']);
        self::assertSame('Ops build webhook', $overview['failed_deliveries'][0]['label']);
        self::assertNull($overview['filter_project']);

        $scoped = $service->build($suspended);
        self::assertSame(0, $scoped['open_issues_total']);
        self::assertSame(1, $scoped['suspended_count']);
        self::assertSame($suspended->getId(), $scoped['filter_project']?->getId());
        self::assertEmpty($scoped['failed_deliveries']);
    }

    public function testAdminOpsPageRendersBuildOutput(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('ops-build-page@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('ops-page-fp');
        $issue->setTitle('Ops page issue');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $em->persist($issue);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops page webhook');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hooks/ops-page');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);
        $destination->recordDeliveryFailure('delivery failed');
        $em->persist($destination);
        $em->flush();

        $this->login($client, $admin);
        $client->request(Request::METHOD_GET, '/admin/ops');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ops overview');
        self::assertSelectorTextContains('body', $project->getName());
        self::assertSelectorTextContains('body', 'Ops page webhook');
        self::assertSelectorTextContains('body', 'delivery failed');
        self::assertStringNotContainsString('https://example.test/hooks/ops-page', (string) $client->getResponse()->getContent());
    }
}
