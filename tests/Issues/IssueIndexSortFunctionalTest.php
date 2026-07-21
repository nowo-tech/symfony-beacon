<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class IssueIndexSortFunctionalTest extends DatabaseWebTestCase
{
    public function testSortAndSearchPersistInQueryString(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('sort-issues@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $older = $this->makeIssue($project, 'Alpha issue', new DateTimeImmutable('-2 days'));
        $newer = $this->makeIssue($project, 'Bravo issue', new DateTimeImmutable('-1 hour'));
        $em->persist($older);
        $em->persist($newer);
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues?sort=title&dir=asc&q=issue&page=1&per_page=25');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('sort=title', (string) $client->getRequest()->getRequestUri());
        self::assertStringContainsString('dir=asc', (string) $client->getRequest()->getRequestUri());
        self::assertStringContainsString('q=issue', (string) $client->getRequest()->getRequestUri());

        self::assertSelectorExists('.issue-table-panel[data-controller="datatable"]');
        self::assertSelectorExists('table.issue-table[data-datatable-target="table"]');
        self::assertSame('title', $crawler->filter('form.issue-filters input[name="sort"]')->attr('value'));
        self::assertSame('asc', $crawler->filter('form.issue-filters input[name="dir"]')->attr('value'));
        self::assertSame('25', $crawler->filter('form.issue-filters select[name="per_page"] option[selected]')->attr('value'));

        $titles = $crawler->filter('table.issue-table tbody tr td:first-child a')->each(
            static fn ($node): string => trim($node->text()),
        );
        self::assertSame(['Alpha issue', 'Bravo issue'], $titles);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues?sort=title&dir=desc&q=issue');
        $titles = $crawler->filter('table.issue-table tbody tr td:first-child a')->each(
            static fn ($node): string => trim($node->text()),
        );
        self::assertSame(['Bravo issue', 'Alpha issue'], $titles);

        self::assertSame('title', $crawler->filter('form.issue-filters input[name="sort"]')->attr('value'));
        self::assertSame('desc', $crawler->filter('form.issue-filters input[name="dir"]')->attr('value'));
        self::assertSame('1', $crawler->filter('form.issue-filters input[name="page"]')->attr('value'));
    }

    public function testServerSidePaginationLimitsRows(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('page-issues@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 12; ++$i) {
            $em->persist($this->makeIssue(
                $project,
                \sprintf('Paged issue %02d', $i),
                new DateTimeImmutable(\sprintf('-%d hours', 13 - $i)),
            ));
        }
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues?sort=title&dir=asc&per_page=10&page=1');
        self::assertResponseIsSuccessful();
        self::assertCount(10, $crawler->filter('table.issue-table tbody tr'));
        self::assertSelectorExists('.table-pagination');
        self::assertSelectorExists('a.table-pagination__link[href*="page=2"]');

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues?sort=title&dir=asc&per_page=10&page=2');
        self::assertCount(2, $crawler->filter('table.issue-table tbody tr'));
    }

    private function makeIssue(Project $project, string $title, DateTimeImmutable $seen): Issue
    {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $title));
        $issue->setTitle($title);
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen($seen);
        $issue->setLastSeen($seen);
        $issue->incrementEventCount();

        return $issue;
    }
}
