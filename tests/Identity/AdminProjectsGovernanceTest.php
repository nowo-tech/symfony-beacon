<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\UserAction;
use App\Identity\UserActionType;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Shared\IssueStatus;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class AdminProjectsGovernanceTest extends DatabaseWebTestCase
{
    public function testAdminCanSuspendAndResumeIngest(): void
    {
        [$client, $admin, $project, $apiKey] = $this->bootWithDemoProject('admin-gov-suspend@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ops stats');

        $token = $crawler->filter('form[action$="/ingest"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/admin/projects/'.$project->getUuid().'/ingest', [
            '_token' => $token,
            'enabled' => '0',
        ]);
        self::assertResponseRedirects('/admin/projects/'.$project->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Ingest suspended');

        $em->clear();
        $project = $em->getRepository(Project::class)->find($project->getId());
        self::assertFalse($project?->isIngestEnabled());

        $envelope = "{\"dsn\":\"https://example.test/1\"}\n{\"type\":\"event\",\"length\":2}\n{}";
        $client->request(
            Request::METHOD_POST,
            '/api/'.$project->getId().'/envelope/',
            server: $this->beaconAuthHeaders($apiKey),
            content: $envelope,
        );
        self::assertResponseStatusCodeSame(403);
        self::assertSame('ingest disabled', $client->getResponse()->getContent());

        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid());
        $token = $crawler->filter('form[action$="/ingest"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/admin/projects/'.$project->getUuid().'/ingest', [
            '_token' => $token,
            'enabled' => '1',
        ]);
        self::assertResponseRedirects();

        $em->clear();
        $project = $em->getRepository(Project::class)->find($project->getId());
        self::assertTrue($project?->isIngestEnabled());

        $actions = $em->getRepository(UserAction::class)->findBy([], ['id' => 'ASC']);
        $types = array_map(static fn (UserAction $a): string => $a->getAction()->value, $actions);
        self::assertContains(UserActionType::ProjectSuspended->value, $types);
        self::assertContains(UserActionType::ProjectResumed->value, $types);
    }

    public function testAdminCanRevokeAndRotateApiKey(): void
    {
        [$client, $admin, $project, $apiKey] = $this->bootWithDemoProject('admin-gov-keys@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();

        $revokeToken = $crawler->filter('form[action$="/revoke"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/keys/'.$apiKey->getId().'/revoke', [
            '_token' => $revokeToken,
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');

        $em->clear();
        $revoked = $em->getRepository(ProjectApiKey::class)->find($apiKey->getId());
        self::assertFalse($revoked?->isActive());

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        // Create a fresh active key via rotate on a newly created key
        $createToken = $crawler->filter('form[action$="/keys"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/keys', [
            '_token' => $createToken,
            'label' => 'rotate-me',
        ]);
        self::assertResponseRedirects();

        $em->clear();
        $active = $em->getRepository(ProjectApiKey::class)->findOneBy([
            'project' => $project->getId(),
            'label' => 'rotate-me',
            'active' => true,
        ]);
        self::assertInstanceOf(ProjectApiKey::class, $active);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        $rotateForm = $crawler->filter('form[action$="/keys/'.$active->getId().'/rotate"]');
        self::assertGreaterThan(0, $rotateForm->count());
        $rotateToken = $rotateForm->filter('input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/keys/'.$active->getId().'/rotate', [
            '_token' => $rotateToken,
        ]);
        self::assertResponseRedirects();

        $em->clear();
        $old = $em->getRepository(ProjectApiKey::class)->find($active->getId());
        self::assertInstanceOf(ProjectApiKey::class, $old);
        self::assertFalse($old->isActive());
        $replacement = $em->getRepository(ProjectApiKey::class)->findOneBy([
            'project' => $project->getId(),
            'label' => 'rotate-me',
            'active' => true,
        ]);
        self::assertInstanceOf(ProjectApiKey::class, $replacement);
        self::assertNotSame($old->getPublicKey(), $replacement->getPublicKey());
    }

    public function testAdminCanSaveGovernanceAndSeeQuotaWarning(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('admin-gov-settings@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('gov-fp');
        $issue->setTitle('Quota');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $em->persist($issue);
        for ($i = 0; $i < 8; ++$i) {
            $event = new Event();
            $event->setIssue($issue);
            $event->setEventId(bin2hex(random_bytes(8)));
            $event->setPayload(['message' => 'n'.$i]);
            $event->setReceivedAt(new DateTimeImmutable());
            $event->setEventTimestamp(new DateTimeImmutable());
            $em->persist($event);
        }
        $project->setEventQuotaDaily(10);
        $em->flush();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Governance');
        self::assertSelectorTextContains('.flash', 'approaching its daily event quota');

        $token = $crawler->filter('form[action$="/governance"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/governance', [
            '_token' => $token,
            'retention_days' => '14',
            'retention_max_events' => '',
            'ingest_rate_limit_per_minute' => '30',
            'event_quota_daily' => '100',
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');

        $em->clear();
        $project = $em->getRepository(Project::class)->find($project->getId());
        self::assertInstanceOf(Project::class, $project);
        self::assertSame(14, $project->getRetentionDays());
        self::assertNull($project->getRetentionMaxEvents());
        self::assertSame(30, $project->getIngestRateLimitPerMinute());
        self::assertSame(100, $project->getEventQuotaDaily());
    }

    public function testViewAsMemberForcesMemberRole(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('admin-gov-viewas@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid());
        $token = $crawler->filter('form[action$="/view-as-member/enable"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/admin/view-as-member/enable', [
            '_token' => $token,
            'redirect' => '/projects/'.$project->getUuid().'/settings',
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'viewing projects as a member');
        self::assertSelectorNotExists('form[action$="/governance"]');
        self::assertSelectorTextContains('body', 'Member');
    }

    public function testAdminIndexShowsStats(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('admin-gov-stats@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('stats-fp');
        $issue->setTitle('Open');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $em->persist($issue);
        $event = new Event();
        $event->setIssue($issue);
        $event->setEventId(bin2hex(random_bytes(8)));
        $event->setPayload([]);
        $event->setReceivedAt(new DateTimeImmutable('-1 day'));
        $event->setEventTimestamp(new DateTimeImmutable('-1 day'));
        $em->persist($event);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $client->request(Request::METHOD_GET, '/admin/projects');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '1 open issues');
        self::assertSelectorTextContains('body', '1 events (7d)');
    }
}
