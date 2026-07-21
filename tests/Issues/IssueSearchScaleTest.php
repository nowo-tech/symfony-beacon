<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\IssueListSort;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class IssueSearchScaleTest extends DatabaseWebTestCase
{
    public function testOccurrenceSortIsGlobalAcrossPages(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('search-scale@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // Distinct 24h counts: High=5, Mid=3, Low=1, Quiet=0 — plus fillers for page 2.
        $high = $this->seedIssueWithRecentEvents($em, $project, 'Occ High', 5, '-1 hour');
        $mid = $this->seedIssueWithRecentEvents($em, $project, 'Occ Mid', 3, '-2 hours');
        $low = $this->seedIssueWithRecentEvents($em, $project, 'Occ Low', 1, '-3 hours');
        $this->seedIssueWithRecentEvents($em, $project, 'Occ Quiet', 0, '-40 days');

        for ($i = 1; $i <= 8; ++$i) {
            $this->seedIssueWithRecentEvents($em, $project, \sprintf('Occ Filler %02d', $i), 0, \sprintf('-%d days', 10 + $i));
        }
        $em->flush();

        self::assertTrue(new IssueListSort('events_24h', 'desc')->isSqlSortable());

        $this->login($client, $user);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues?sort=events_24h&dir=desc&per_page=10&page=1&status=',
        );
        self::assertResponseIsSuccessful();
        self::assertCount(10, $crawler->filter('table.issue-table tbody tr'));

        $titlesPage1 = $crawler->filter('table.issue-table tbody tr td:first-child a')->each(
            static fn ($node): string => trim($node->text()),
        );
        self::assertSame(['Occ High', 'Occ Mid', 'Occ Low'], \array_slice($titlesPage1, 0, 3));

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues?sort=events_24h&dir=desc&per_page=10&page=2&status=',
        );
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('table.issue-table tbody tr'));

        $titlesPage2 = $crawler->filter('table.issue-table tbody tr td:first-child a')->each(
            static fn ($node): string => trim($node->text()),
        );
        self::assertNotContains('Occ High', $titlesPage2);
        self::assertNotContains('Occ Mid', $titlesPage2);
        self::assertNotContains('Occ Low', $titlesPage2);

        // Repository-level check: SQL order matches fixture counts (not a page-local resort).
        /** @var IssueRepository $repo */
        $repo = $em->getRepository(Issue::class);
        $ordered = $repo->search(
            $project,
            sort: new IssueListSort('events_24h', 'desc'),
            limit: 3,
            offset: 0,
        );
        self::assertSame(
            [$high->getId(), $mid->getId(), $low->getId()],
            array_map(static fn (Issue $i): ?int => $i->getId(), $ordered),
        );
    }

    public function testTagUrlUserAndReleaseFiltersCombineWithSearch(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('search-filters@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $tagged = $this->makeIssue($project, 'Chrome crash tagged');
        $tagged->setLastRelease('9.0.0');
        $tagged->setFirstRelease('9.0.0');
        $em->persist($tagged);
        $this->addEvent($em, $tagged, new DateTimeImmutable('-1 hour'), [
            'tags' => ['browser' => 'chrome'],
            'request' => ['url' => 'https://app.example/checkout'],
        ], 'alice@example.com');

        $other = $this->makeIssue($project, 'Other browser issue');
        $other->setLastRelease('8.0.0');
        $other->setFirstRelease('8.0.0');
        $em->persist($other);
        $this->addEvent($em, $other, new DateTimeImmutable('-1 hour'), [
            'tags' => ['browser' => 'firefox'],
            'request' => ['url' => 'https://app.example/home'],
        ], 'bob@example.com');

        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues?status=&q=crash&tag=chrome&url=checkout&user=alice&release=9.0.0',
        );
        self::assertResponseIsSuccessful();
        $body = $crawler->text();
        self::assertStringContainsString('Chrome crash tagged', $body);
        self::assertStringNotContainsString('Other browser issue', $body);

        self::assertSame('chrome', $crawler->filter('form.issue-filters input[name="tag"]')->attr('value'));
        self::assertSame('checkout', $crawler->filter('form.issue-filters input[name="url"]')->attr('value'));
        self::assertSame('alice', $crawler->filter('form.issue-filters input[name="user"]')->attr('value'));

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues?status=&tag=unknown-tag-xyz',
        );
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('No issues match these filters.', $crawler->text());
        self::assertCount(0, $crawler->filter('table.issue-table tbody tr'));
    }

    public function testPerPageCapRejectsInvalidValues(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('search-perpage@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 30; ++$i) {
            $em->persist($this->makeIssue($project, \sprintf('Cap issue %02d', $i), new DateTimeImmutable(\sprintf('-%d minutes', $i))));
        }
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues?status=&per_page=999&page=1',
        );
        self::assertResponseIsSuccessful();
        self::assertCount(25, $crawler->filter('table.issue-table tbody tr'));
        self::assertSame('25', $crawler->filter('form.issue-filters select[name="per_page"] option[selected]')->attr('value'));

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues?status=&per_page=10&page=1',
        );
        self::assertCount(10, $crawler->filter('table.issue-table tbody tr'));
    }

    public function testSevenAndThirtyDayOccurrenceSorts(): void
    {
        [, , $project] = $this->bootWithDemoProject('search-windows@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $hot7 = $this->seedIssueWithRecentEvents($em, $project, 'Window Hot7', 4, '-2 days');
        $warm30 = $this->seedIssueWithRecentEvents($em, $project, 'Window Warm30', 2, '-10 days');
        $cold = $this->seedIssueWithRecentEvents($em, $project, 'Window Cold', 1, '-40 days');
        $em->flush();

        /** @var IssueRepository $repo */
        $repo = $em->getRepository(Issue::class);

        $by7 = $repo->search(
            $project,
            sort: new IssueListSort('events_7d', 'desc'),
            limit: 10,
        );
        self::assertSame($hot7->getId(), $by7[0]->getId());

        $by30 = $repo->search(
            $project,
            sort: new IssueListSort('events_30d', 'desc'),
            limit: 10,
        );
        self::assertSame(
            [$hot7->getId(), $warm30->getId(), $cold->getId()],
            array_map(static fn (Issue $i): ?int => $i->getId(), \array_slice($by30, 0, 3)),
        );
    }

    private function seedIssueWithRecentEvents(
        EntityManagerInterface $em,
        Project $project,
        string $title,
        int $recentCount,
        string $relativeSeen,
    ): Issue {
        $seen = new DateTimeImmutable($relativeSeen);
        $issue = $this->makeIssue($project, $title, $seen);
        $em->persist($issue);

        for ($i = 0; $i < $recentCount; ++$i) {
            $at = $seen->modify(\sprintf('-%d minutes', $i));
            $this->addEvent($em, $issue, $at, ['message' => $title.' #'.$i]);
            $issue->incrementEventCount();
        }

        if (0 === $recentCount) {
            // One old event so the issue still has history without affecting 24h/7d windows.
            $this->addEvent($em, $issue, new DateTimeImmutable('-40 days'), ['message' => $title.' old']);
            $issue->incrementEventCount();
        }

        return $issue;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function addEvent(
        EntityManagerInterface $em,
        Issue $issue,
        DateTimeImmutable $at,
        array $payload,
        ?string $userIdentifier = null,
    ): Event {
        $event = new Event();
        $event->setIssue($issue);
        $event->setEventId(bin2hex(random_bytes(8)));
        $event->setPayload($payload);
        $event->setReceivedAt($at);
        $event->setEventTimestamp($at);
        if (null !== $userIdentifier) {
            $event->setUserIdentifier($userIdentifier);
        }
        $em->persist($event);

        return $event;
    }

    private function makeIssue(Project $project, string $title, ?DateTimeImmutable $seen = null): Issue
    {
        $seen ??= new DateTimeImmutable('-1 hour');
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $title.microtime(true).random_int(1, 1_000_000)));
        $issue->setTitle($title);
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen($seen);
        $issue->setLastSeen($seen);

        return $issue;
    }
}
