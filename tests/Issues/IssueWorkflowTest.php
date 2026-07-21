<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Issues\Entity\IssueSavedView;
use App\Shared\IssuePriority;
use App\Shared\IssueStatus;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class IssueWorkflowTest extends DatabaseWebTestCase
{
    public function testPriorityCommentDuplicateAndSavedView(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('workflow-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $canonical = new Issue();
        $canonical->setProject($project);
        $canonical->setFingerprint(hash('sha256', 'workflow-canonical'));
        $canonical->setTitle('Canonical issue');
        $canonical->setCulprit('demo');
        $canonical->setLevel('error');
        $canonical->setFirstSeen(new DateTimeImmutable());
        $canonical->setLastSeen(new DateTimeImmutable());
        $canonical->incrementEventCount();

        $duplicate = new Issue();
        $duplicate->setProject($project);
        $duplicate->setFingerprint(hash('sha256', 'workflow-duplicate'));
        $duplicate->setTitle('Duplicate candidate');
        $duplicate->setCulprit('demo');
        $duplicate->setLevel('error');
        $duplicate->setFirstSeen(new DateTimeImmutable());
        $duplicate->setLastSeen(new DateTimeImmutable());
        $duplicate->incrementEventCount();

        $em->persist($canonical);
        $em->persist($duplicate);
        $em->flush();

        self::assertSame(IssuePriority::Medium, $duplicate->getPriority());

        $this->login($client, $owner);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid(),
        );
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.issue-priority-form');
        self::assertSelectorExists('[data-testid="issue-comments"]');
        self::assertSelectorExists('[data-testid="mark-duplicate"]');

        $priorityToken = $crawler->filter('form.issue-priority-form input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid().'/priority', [
            '_token' => $priorityToken,
            'priority' => IssuePriority::Critical->value,
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('.issue-badge--priority', 'Critical');

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($duplicate->getId());
        self::assertSame(IssuePriority::Critical, $reloaded->getPriority());

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues?priority=critical');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Duplicate candidate');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid().'/priority', [
            '_token' => $priorityToken,
            'priority' => 'not-a-priority',
        ]);
        self::assertResponseRedirects();
        $em->clear();
        $reloaded = $em->getRepository(Issue::class)->find($duplicate->getId());
        self::assertSame(IssuePriority::Critical, $reloaded->getPriority());

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid(),
        );
        $commentToken = $crawler->filter('form.issue-comments__form input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid().'/comments', [
            '_token' => $commentToken,
            'body' => '',
        ]);
        self::assertResponseRedirects();
        self::assertSame(0, $em->getRepository(IssueComment::class)->count(['issue' => $duplicate]));

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid().'/comments', [
            '_token' => $commentToken,
            'body' => 'Needs triage from the team.',
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('[data-testid="issue-comments"]', 'Needs triage from the team.');
        self::assertSelectorTextContains('[data-testid="issue-comments"]', 'Test User');

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid(),
        );
        $dupToken = $crawler->filter('form.issue-duplicate-form input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid().'/duplicate', [
            '_token' => $dupToken,
            'canonical_uuid' => $duplicate->getUuid(),
        ]);
        self::assertResponseRedirects();
        $em->clear();
        $reloaded = $em->getRepository(Issue::class)->find($duplicate->getId());
        self::assertNull($reloaded->getDuplicateOf());
        self::assertSame(IssueStatus::Unresolved, $reloaded->getStatus());

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid().'/duplicate', [
            '_token' => $dupToken,
            'canonical_uuid' => $canonical->getUuid(),
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid());
        $client->followRedirect();
        self::assertSelectorExists('[data-testid="duplicate-of"]');
        self::assertSelectorTextContains('[data-testid="duplicate-of"]', 'Canonical issue');

        $em->clear();
        $reloaded = $em->getRepository(Issue::class)->find($duplicate->getId());
        self::assertSame(IssueStatus::Ignored, $reloaded->getStatus());
        self::assertSame($canonical->getId(), $reloaded->getDuplicateOf()?->getId());

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues?priority=critical&status=ignored');
        self::assertResponseIsSuccessful();
        $viewToken = $crawler->filter('form[action*="/issues/views"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/views', [
            '_token' => $viewToken,
            'name' => 'Critical ignored',
            'priority' => 'critical',
            'status' => 'ignored',
            'sort' => 'last_seen',
            'dir' => 'desc',
            'per_page' => '25',
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();

        /** @var list<IssueSavedView> $views */
        $views = $em->getRepository(IssueSavedView::class)->findBy(['user' => $owner, 'project' => $project]);
        self::assertCount(1, $views);
        self::assertSame('Critical ignored', $views[0]->getName());
        self::assertSame('critical', $views[0]->getQueryJson()['priority'] ?? null);

        $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/views/'.$views[0]->getUuid(),
        );
        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString('priority=critical', $location);
        self::assertStringContainsString('status=ignored', $location);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        $deleteToken = $crawler->filter('form[action*="/delete"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/views/'.$views[0]->getUuid().'/delete', [
            '_token' => $deleteToken,
        ]);
        self::assertResponseRedirects();
        $em->clear();
        self::assertSame(0, $em->getRepository(IssueSavedView::class)->count(['user' => $owner, 'project' => $project]));
    }

    public function testMergeEventsIntoCanonical(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('workflow-merge@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $canonical = new Issue();
        $canonical->setProject($project);
        $canonical->setFingerprint(hash('sha256', 'workflow-merge-canonical'));
        $canonical->setTitle('Merge canonical');
        $canonical->setCulprit('demo');
        $canonical->setLevel('error');
        $canonical->setFirstSeen(new DateTimeImmutable('-2 hours'));
        $canonical->setLastSeen(new DateTimeImmutable('-2 hours'));
        $canonical->setEventCount(1);

        $duplicate = new Issue();
        $duplicate->setProject($project);
        $duplicate->setFingerprint(hash('sha256', 'workflow-merge-duplicate'));
        $duplicate->setTitle('Merge duplicate');
        $duplicate->setCulprit('demo');
        $duplicate->setLevel('error');
        $duplicate->setFirstSeen(new DateTimeImmutable('-1 hour'));
        $duplicate->setLastSeen(new DateTimeImmutable('-30 minutes'));
        $duplicate->setEventCount(2);

        $em->persist($canonical);
        $em->persist($duplicate);
        $em->flush();

        $minimalPayload = ['message' => 'merge-test'];

        $canonEvent = new Event();
        $canonEvent->setIssue($canonical);
        $canonEvent->setEventId(bin2hex(random_bytes(8)));
        $canonEvent->setPayload($minimalPayload);
        $canonEvent->setReceivedAt(new DateTimeImmutable('-2 hours'));
        $canonEvent->setEventTimestamp(new DateTimeImmutable('-2 hours'));
        $canonEvent->setEnvironment('production');
        $canonEvent->setReleaseVersion('1.0.0');

        $dupEarly = new Event();
        $dupEarly->setIssue($duplicate);
        $dupEarly->setEventId(bin2hex(random_bytes(8)));
        $dupEarly->setPayload($minimalPayload);
        $dupEarly->setReceivedAt(new DateTimeImmutable('-1 hour'));
        $dupEarly->setEventTimestamp(new DateTimeImmutable('-1 hour'));
        $dupEarly->setEnvironment('staging');
        $dupEarly->setReleaseVersion('1.1.0');

        $dupLate = new Event();
        $dupLate->setIssue($duplicate);
        $dupLate->setEventId(bin2hex(random_bytes(8)));
        $dupLate->setPayload($minimalPayload);
        $dupLate->setReceivedAt(new DateTimeImmutable('-30 minutes'));
        $dupLate->setEventTimestamp(new DateTimeImmutable('-30 minutes'));
        $dupLate->setEnvironment('production');
        $dupLate->setReleaseVersion('1.2.0');

        $em->persist($canonEvent);
        $em->persist($dupEarly);
        $em->persist($dupLate);
        $em->flush();

        $this->login($client, $owner);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid(),
        );
        $dupToken = $crawler->filter('form.issue-duplicate-form input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$duplicate->getUuid().'/duplicate', [
            '_token' => $dupToken,
            'canonical_uuid' => $canonical->getUuid(),
            'merge_events' => '1',
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues/'.$canonical->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Events merged into the canonical issue');

        $em->clear();
        /** @var Issue $mergedCanonical */
        $mergedCanonical = $em->getRepository(Issue::class)->find($canonical->getId());
        /** @var Issue $mergedDuplicate */
        $mergedDuplicate = $em->getRepository(Issue::class)->find($duplicate->getId());

        self::assertSame(3, $mergedCanonical->getEventCount());
        self::assertSame(0, $mergedDuplicate->getEventCount());
        self::assertSame(IssueStatus::Ignored, $mergedDuplicate->getStatus());
        self::assertSame($mergedCanonical->getId(), $mergedDuplicate->getDuplicateOf()?->getId());
        self::assertSame(3, $em->getRepository(Event::class)->count(['issue' => $mergedCanonical]));
        self::assertSame(0, $em->getRepository(Event::class)->count(['issue' => $mergedDuplicate]));
        self::assertSame('1.0.0', $mergedCanonical->getFirstRelease());
        self::assertSame('1.2.0', $mergedCanonical->getLastRelease());
        self::assertSame('production', $mergedCanonical->getLastEnvironment());
    }
}
